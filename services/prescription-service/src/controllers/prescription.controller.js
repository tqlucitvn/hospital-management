const { PrismaClient } = require('@prisma/client');
const prisma = new PrismaClient();
const { publishEvent } = require('../lib/broker');

const EXCHANGE = 'prescription.events';
const VALID_STATUS = ['ISSUED', 'PENDING', 'DISPENSED', 'COMPLETED', 'CANCELED'];
const TRANSITIONS = {
    ISSUED: ['PENDING', 'DISPENSED', 'COMPLETED', 'CANCELED'],
    PENDING: ['DISPENSED', 'COMPLETED', 'CANCELED'],
    DISPENSED: ['COMPLETED', 'CANCELED'],
    COMPLETED: [],
    CANCELED: []
};

exports.create = async (req, res) => {
    try {
        const { patientId, doctorId, appointmentId, note, items } = req.body;
        if (!patientId || !doctorId || !Array.isArray(items) || items.length === 0)
            return res.status(400).json({ error: 'patientId, doctorId, items required' });

        // Basic item validation
        for (const it of items) {
            if (!it.drugName || !it.dosage || !it.frequency || typeof it.durationDays !== 'number') {
                return res.status(400).json({ error: 'Invalid item fields' });
            }
            if (it.durationDays <= 0) {
                return res.status(400).json({ error: 'durationDays must be > 0' });
            }
        }

        const created = await prisma.prescription.create({
            data: {
                patientId,
                doctorId,
                appointmentId,
                note,
                items: {
                    create: items.map(i => ({
                        drugName: i.drugName,
                        dosage: i.dosage,
                        frequency: i.frequency,
                        durationDays: i.durationDays,
                        instruction: i.instruction
                    }))
                }
            },
            include: { items: true }
        });

        publishEvent(EXCHANGE, 'prescription.created', {
            type: 'prescription.created',
            id: created.id,
            patientId,
            doctorId,
            itemsCount: created.items.length,
            status: created.status,
            correlationId: req.correlationId,
            requestId: req.requestId,
            ts: new Date().toISOString()
        }).catch(() => { });

        res.status(201).json(created);
    } catch (e) {
        res.status(500).json({ error: 'Create failed', details: e.message });
    }
};

exports.getOne = async (req, res) => {
    try {
        const { id } = req.params;
        const p = await prisma.prescription.findUnique({
            where: { id },
            include: { items: true }
        });
        if (!p) return res.status(404).json({ error: 'Not found' });
        
        // If user is a Doctor, they can only see their own prescriptions
        if (req.user && req.user.role === 'DOCTOR' && req.user.id !== p.doctorId) {
            return res.status(403).json({ error: 'Doctors can only view their own prescriptions' });
        }
        
        res.json(p);
    } catch {
        res.status(500).json({ error: 'Fetch failed' });
    }
};

exports.update = async (req, res) => {
    try {
        const { id } = req.params;
        const { patientId, doctorId, appointmentId, note, items } = req.body;
        
        if (!patientId || !doctorId || !Array.isArray(items) || items.length === 0) {
            return res.status(400).json({ error: 'patientId, doctorId, items required' });
        }

        // Check if prescription exists
        const existing = await prisma.prescription.findUnique({
            where: { id },
            select: { id: true, doctorId: true, status: true }
        });
        
        if (!existing) {
            return res.status(404).json({ error: 'Prescription not found' });
        }

        // If user is a Doctor, they can only edit their own prescriptions
        if (req.user && req.user.role === 'DOCTOR' && req.user.id !== existing.doctorId) {
            return res.status(403).json({ error: 'Doctors can only edit their own prescriptions' });
        }

        // Cannot edit if already DISPENSED or COMPLETED
        if (existing.status === 'DISPENSED' || existing.status === 'COMPLETED') {
            return res.status(409).json({ error: 'Cannot edit dispensed or completed prescriptions' });
        }

        // Validate items
        for (const it of items) {
            if (!it.drugName || !it.dosage || !it.frequency || typeof it.durationDays !== 'number') {
                return res.status(400).json({ error: 'Invalid item fields' });
            }
            if (it.durationDays <= 0) {
                return res.status(400).json({ error: 'durationDays must be > 0' });
            }
        }

        // Update prescription with transaction
        const updated = await prisma.$transaction(async (prisma) => {
            // Delete old items
            await prisma.prescriptionItem.deleteMany({
                where: { prescriptionId: id }
            });

            // Update prescription with new data
            return await prisma.prescription.update({
                where: { id },
                data: {
                    patientId,
                    doctorId,
                    appointmentId,
                    note,
                    items: {
                        create: items.map(i => ({
                            drugName: i.drugName,
                            dosage: i.dosage,
                            frequency: i.frequency,
                            durationDays: i.durationDays,
                            instruction: i.instruction || null
                        }))
                    }
                },
                include: { items: true }
            });
        });

        publishEvent(EXCHANGE, 'prescription.updated', {
            type: 'prescription.updated',
            id: updated.id,
            patientId: updated.patientId,
            doctorId: updated.doctorId,
            itemsCount: updated.items.length,
            status: updated.status,
            correlationId: req.correlationId,
            requestId: req.requestId,
            ts: new Date().toISOString()
        }).catch(() => { });

        res.json(updated);
    } catch (e) {
        console.error('Update prescription error:', e);
        if (e.code === 'P2025') {
            return res.status(404).json({ error: 'Prescription not found' });
        }
        res.status(500).json({ error: 'Update failed', details: e.message });
    }
};

exports.list = async (req, res) => {
    try {
        const { patientId, page = 1, limit = 20, search } = req.query;
        const skip = (parseInt(page) - 1) * parseInt(limit);
        const take = parseInt(limit);
        
        let where = {};
        
        // If user is a Doctor, they can only see their own prescriptions
        if (req.user && req.user.role === 'DOCTOR') {
            where.doctorId = req.user.id;
        }
        
        // Add patientId filter if provided
        if (patientId) {
            where.patientId = patientId;
        }
        
        // Add search functionality
        if (search) {
            where.OR = [
                { patientId: { contains: search, mode: 'insensitive' } },
                { doctorId: { contains: search, mode: 'insensitive' } },
                { note: { contains: search, mode: 'insensitive' } }
            ];
        }
        
        // Check if pagination is requested
        const isPaginated = req.query.page || req.query.limit;
        
        if (isPaginated) {
            // Return paginated response for frontend list view
            const [prescriptions, total] = await Promise.all([
                prisma.prescription.findMany({
                    where,
                    orderBy: { createdAt: 'desc' },
                    select: {
                        id: true,
                        patientId: true,
                        doctorId: true,
                        status: true,
                        createdAt: true,
                        updatedAt: true,
                        _count: { select: { items: true } }
                    },
                    skip,
                    take
                }),
                prisma.prescription.count({ where })
            ]);
            
            const mapped = prescriptions.map(p => ({
                id: p.id,
                patientId: p.patientId,
                doctorId: p.doctorId,
                status: p.status,
                createdAt: p.createdAt,
                updatedAt: p.updatedAt,
                itemsCount: p._count.items
            }));
            
            res.json({
                prescriptions: mapped,
                total,
                page: parseInt(page),
                totalPages: Math.ceil(total / take)
            });
        } else {
            // Return simple array for legacy compatibility
            const rows = await prisma.prescription.findMany({
                where,
                orderBy: { createdAt: 'desc' },
                select: {
                    id: true,
                    patientId: true,
                    doctorId: true,
                    status: true,
                    createdAt: true,
                    updatedAt: true,
                    _count: { select: { items: true } }
                }
            });
            
            const mapped = rows.map(p => ({
                id: p.id,
                patientId: p.patientId,
                doctorId: p.doctorId,
                status: p.status,
                createdAt: p.createdAt,
                updatedAt: p.updatedAt,
                itemsCount: p._count.items
            }));
            
            res.json(mapped);
        }
    } catch (e) {
        console.error('List prescriptions error:', e);
        res.status(500).json({ error: 'List failed', details: e.message });
    }
};

exports.updateStatus = async (req, res) => {
    try {
        const { id } = req.params;
        const { status } = req.body;
        if (!status || !VALID_STATUS.includes(status))
            return res.status(400).json({ error: 'Invalid status' });

        const current = await prisma.prescription.findUnique({
            where: { id },
            select: { status: true, dispensedBy: true, dispensedAt: true }
        });
        if (!current) return res.status(404).json({ error: 'Not found' });

        if (!TRANSITIONS[current.status].includes(status)) {
            if (current.status === status) {
                return res.status(200).json({ id, status }); // idempotent
            }
            return res.status(409).json({
                error: 'Invalid transition',
                from: current.status,
                allowed: TRANSITIONS[current.status]
            });
        }

        // Role-based restriction logic:
        // NURSE: may only transition to DISPENSED from ISSUED or PENDING
        // DOCTOR: may perform any transition except cannot set DISPENSED (reserved for nurse/admin) unless they are also ADMIN
        // ADMIN: full access
        if (req.user) {
            const role = req.user.role;
            if (role === 'NURSE') {
                const allowedForNurse = ['DISPENSED'];
                if (!allowedForNurse.includes(status) || !['ISSUED', 'PENDING'].includes(current.status)) {
                    return res.status(403).json({ error: 'Nurse can only mark prescription as DISPENSED from ISSUED or PENDING' });
                }
            } else if (role === 'DOCTOR') {
                // Prevent doctor from setting DISPENSED (operational separation)
                if (status === 'DISPENSED') {
                    return res.status(403).json({ error: 'Doctor cannot mark as DISPENSED (pharmacy/nurse action)' });
                }
            }
        }

        const dataUpdate = { status };
        if (status === 'DISPENSED') {
            dataUpdate.dispensedBy = req.user ? req.user.id : null;
            dataUpdate.dispensedAt = new Date();
        }

        const upd = await prisma.prescription.update({
            where: { id },
            data: dataUpdate,
            select: { id: true, status: true, updatedAt: true, dispensedBy: true, dispensedAt: true }
        });

        publishEvent(EXCHANGE, 'prescription.statusUpdated', {
            type: 'prescription.statusUpdated',
            id,
            status,
            correlationId: req.correlationId,
            requestId: req.requestId,
            ts: new Date().toISOString()
        }).catch(() => { });

        res.json(upd);
    } catch (e) {
        if (e.code === 'P2025') return res.status(404).json({ error: 'Not found' });
        res.status(500).json({ error: 'Update failed' });
    }
};

exports.delete = async (req, res) => {
    try {
        const { id } = req.params;
        
        // Check if prescription exists
        const existing = await prisma.prescription.findUnique({
            where: { id },
            select: { id: true }
        });
        
        if (!existing) {
            return res.status(404).json({ error: 'Prescription not found' });
        }
        
        // Delete prescription (items will be deleted via cascade)
        await prisma.prescription.delete({
            where: { id }
        });
        
        publishEvent(EXCHANGE, 'prescription.deleted', {
            type: 'prescription.deleted',
            id,
            correlationId: req.correlationId,
            requestId: req.requestId,
            ts: new Date().toISOString()
        }).catch(() => { });
        
        res.status(204).send();
    } catch (e) {
        if (e.code === 'P2025') {
            return res.status(404).json({ error: 'Prescription not found' });
        }
        res.status(500).json({ error: 'Delete failed' });
    }
};