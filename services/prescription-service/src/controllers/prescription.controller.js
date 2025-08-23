const { PrismaClient } = require('@prisma/client');
const prisma = new PrismaClient();
const { publishEvent } = require('../lib/broker');

const EXCHANGE = 'prescription.events';
const VALID_STATUS = ['ISSUED', 'FILLED', 'CANCELED'];
const TRANSITIONS = {
    ISSUED: ['FILLED', 'CANCELED'],
    FILLED: [],
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
        res.json(p);
    } catch {
        res.status(500).json({ error: 'Fetch failed' });
    }
};

exports.list = async (req, res) => {
    try {
        const { patientId } = req.query;
        const where = patientId ? { patientId } : {};
        const rows = await prisma.prescription.findMany({
            where,
            orderBy: { createdAt: 'desc' },
            select: { id: true, patientId: true, doctorId: true, status: true, createdAt: true, updatedAt: true }
        });
        res.json(rows);
    } catch {
        res.status(500).json({ error: 'List failed' });
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
            select: { status: true }
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

        const upd = await prisma.prescription.update({
            where: { id },
            data: { status },
            select: { id: true, status: true, updatedAt: true }
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