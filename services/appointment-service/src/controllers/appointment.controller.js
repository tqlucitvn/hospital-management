const { PrismaClient } = require('@prisma/client');
const prisma = new PrismaClient();
const { publishEvent } = require('../lib/broker');

const EXCHANGE = 'appointment.events';
const VALID_STATUS = ['SCHEDULED', 'CONFIRMED', 'COMPLETED', 'CANCELED'];
const TRANSITIONS = {
    SCHEDULED: ['CONFIRMED', 'CANCELED'],
    CONFIRMED: ['COMPLETED', 'CANCELED'],
    COMPLETED: [],
    CANCELED: []
};

function parseDate(value) {
    const d = new Date(value);
    if (isNaN(d.getTime())) return null;
    return d;
}

exports.create = async (req, res, next) => {
    try {
        const { patientId, doctorId, startTime, endTime, reason } = req.body;
        if (!patientId || !doctorId || !startTime || !endTime)
            return res.status(400).json({ error: 'Missing fields' });

        const start = parseDate(startTime);
        const end = parseDate(endTime);
        if (!start || !end) return res.status(400).json({ error: 'Invalid datetime format' });
        if (end <= start) return res.status(400).json({ error: 'Invalid time range' });

        // Check overlap for same doctor
        const overlap = await prisma.appointment.findFirst({
            where: {
                doctorId,
                startTime: { lt: end },
                endTime: { gt: start }
            }
        });

        if (overlap) {
            return res.status(409).json({ error: 'Doctor timeslot conflict' });
        }

        const appt = await prisma.appointment.create({
            data: { patientId, doctorId, startTime: start, endTime: end, reason }
        });

        publishEvent(EXCHANGE, 'appointment.created', {
            type: 'appointment.created',
            id: appt.id,
            patientId,
            doctorId,
            startTime: appt.startTime,
            endTime: appt.endTime,
            reason: appt.reason,
            status: appt.status,
            ts: new Date().toISOString()
        }).catch(() => { });

        res.status(201).json(appt);
    } catch (e) {
        next(e);
    }
};

exports.list = async (_req, res, next) => {
    try {
        const rows = await prisma.appointment.findMany({ orderBy: { startTime: 'asc' } });
        res.json(rows);
    } catch (e) { next(e); }
};

exports.updateStatus = async (req, res, next) => {
    try {
        const id = req.params.id;
        const { status } = req.body;
        
        console.log('updateStatus called with ID:', id, 'status:', status);
        
        if (!id) {
            return res.status(400).json({ error: 'ID parameter is required' });
        }
        
        if (!status || !VALID_STATUS.includes(status))
            return res.status(400).json({ error: 'Invalid status' });

        // Lấy status hiện tại
        const current = await prisma.appointment.findUnique({
            where: { id },
            select: { status: true, patientId: true, doctorId: true, startTime: true, endTime: true }
        });
        if (!current) return res.status(404).json({ error: 'Not found' });

        // Idempotent
        if (current.status === status) {
            return res.json({ id, ...current });
        }

        // Kiểm tra chuyển trạng thái hợp lệ
        if (!TRANSITIONS[current.status].includes(status)) {
            return res.status(409).json({
                error: 'Invalid status transition',
                from: current.status,
                to: status,
                allowed: TRANSITIONS[current.status]
            });
        }

        const appt = await prisma.appointment.update({
            where: { id },
            data: { status }
        });

        publishEvent(EXCHANGE, 'appointment.statusUpdated', {
            type: 'appointment.statusUpdated',
            id,
            status,
            ts: new Date().toISOString()
        }).catch(() => { });

        res.json(appt);
    } catch (e) {
        if (e.code === 'P2025') return res.status(404).json({ error: 'Not found' });
        next(e);
    }
};

exports.getById = async (req, res, next) => {
    try {
        const id = req.params.id;
        console.log('getById called with ID:', id, 'type:', typeof id);
        
        if (!id) {
            return res.status(400).json({ error: 'ID parameter is required' });
        }
        
        const appointment = await prisma.appointment.findUnique({
            where: { id: id }
        });
        
        if (!appointment) {
            return res.status(404).json({ error: 'Appointment not found' });
        }
        
        res.json(appointment);
    } catch (e) {
        console.error('getById error:', e);
        next(e);
    }
};

exports.update = async (req, res, next) => {
    try {
        const id = req.params.id;
        const { patientId, doctorId, startTime, endTime, reason } = req.body;
        
        if (!id) {
            return res.status(400).json({ error: 'ID parameter is required' });
        }
        
        if (!patientId || !doctorId || !startTime || !endTime) {
            return res.status(400).json({ error: 'Missing fields' });
        }

        const start = parseDate(startTime);
        const end = parseDate(endTime);
        if (!start || !end) return res.status(400).json({ error: 'Invalid datetime format' });
        if (end <= start) return res.status(400).json({ error: 'Invalid time range' });

        // Check if appointment exists
        const existing = await prisma.appointment.findUnique({
            where: { id: id }
        });
        
        if (!existing) {
            return res.status(404).json({ error: 'Appointment not found' });
        }

        // Check overlap for same doctor (excluding current appointment)
        const overlap = await prisma.appointment.findFirst({
            where: {
                doctorId,
                startTime: { lt: end },
                endTime: { gt: start },
                id: { not: id }
            }
        });

        if (overlap) {
            return res.status(409).json({ error: 'Doctor timeslot conflict' });
        }

        const appointment = await prisma.appointment.update({
            where: { id: id },
            data: { patientId, doctorId, startTime: start, endTime: end, reason }
        });

        publishEvent(EXCHANGE, 'appointment.updated', {
            type: 'appointment.updated',
            id: appointment.id,
            patientId: appointment.patientId,
            doctorId: appointment.doctorId,
            startTime: appointment.startTime,
            endTime: appointment.endTime,
            ts: new Date().toISOString()
        }).catch(() => { });

        res.json(appointment);
    } catch (e) {
        next(e);
    }
};

exports.delete = async (req, res, next) => {
    try {
        const id = req.params.id;
        
        if (!id) {
            return res.status(400).json({ error: 'ID parameter is required' });
        }
        
        await prisma.appointment.delete({ where: { id } });

        publishEvent(EXCHANGE, 'appointment.deleted', {
            type: 'appointment.deleted',
            id,
            ts: new Date().toISOString()
        }).catch(() => { });
        res.status(204).send();
    } catch (e) {
        if (e.code === 'P2025') return res.status(404).json({ error: 'Not found' });
        next(e);
    }
};