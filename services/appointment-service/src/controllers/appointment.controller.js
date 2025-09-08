const { PrismaClient } = require('@prisma/client');
const prisma = new PrismaClient();
const { publishEvent } = require('../lib/broker');

const EXCHANGE = 'appointment.events';
const VALID_STATUS = ['SCHEDULED', 'CONFIRMED', 'COMPLETED', 'CANCELED'];

// Standard forward transitions
const TRANSITIONS = {
    SCHEDULED: ['CONFIRMED', 'CANCELED'],
    CONFIRMED: ['COMPLETED', 'CANCELED'],
    COMPLETED: [],
    CANCELED: []
};

// Administrative rollback transitions (with conditions)
const ADMIN_ROLLBACK_TRANSITIONS = {
    CONFIRMED: ['SCHEDULED'],  // Admin can rollback CONFIRMED → SCHEDULED
    COMPLETED: ['CONFIRMED']   // Admin can rollback COMPLETED → CONFIRMED (within time window)
};

// Time window for rollback (in minutes)
const ROLLBACK_TIME_WINDOW = 30;

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

        // Check overlap for same doctor, chỉ với các lịch chưa hoàn thành/hủy
        const overlap = await prisma.appointment.findFirst({
            where: {
                doctorId,
                startTime: { lt: end },
                endTime: { gt: start },
                status: { in: ['SCHEDULED', 'CONFIRMED'] }
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
        let where = {};
        // Nếu user là Doctor, chỉ trả về appointment của chính mình
        if (_req.user && _req.user.role === 'DOCTOR' && _req.user.id) {
            where.doctorId = _req.user.id;
        }
        const rows = await prisma.appointment.findMany({ where, orderBy: { startTime: 'asc' } });
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
        let isValidTransition = false;
        let rollbackReason = null;

        // Check standard forward transitions
        if (TRANSITIONS[current.status].includes(status)) {
            isValidTransition = true;
        }
        // Check administrative rollback transitions
        else if (req.user && req.user.role === 'ADMIN' && ADMIN_ROLLBACK_TRANSITIONS[current.status]?.includes(status)) {
            // Additional checks for rollback
            if (current.status === 'COMPLETED') {
                // Check time window for COMPLETED → CONFIRMED rollback
                const updatedAt = await prisma.appointment.findUnique({
                    where: { id },
                    select: { updatedAt: true }
                });
                const timeSinceUpdate = (new Date() - new Date(updatedAt.updatedAt)) / (1000 * 60); // minutes
                
                if (timeSinceUpdate <= ROLLBACK_TIME_WINDOW) {
                    isValidTransition = true;
                    rollbackReason = `Admin rollback within ${ROLLBACK_TIME_WINDOW} minute window`;
                } else {
                    return res.status(409).json({
                        error: 'Rollback time window expired',
                        timeWindow: `${ROLLBACK_TIME_WINDOW} minutes`,
                        timeSinceUpdate: `${Math.round(timeSinceUpdate)} minutes`
                    });
                }
            } else {
                isValidTransition = true;
                rollbackReason = 'Admin rollback authorization';
            }
        }

        if (!isValidTransition) {
            return res.status(409).json({
                error: 'Invalid status transition',
                from: current.status,
                to: status,
                allowed: TRANSITIONS[current.status],
                adminRollback: req.user?.role === 'ADMIN' ? ADMIN_ROLLBACK_TRANSITIONS[current.status] : null
            });
        }

        const appt = await prisma.appointment.update({
            where: { id },
            data: { status }
        });

        // Enhanced audit logging for rollbacks
        const eventData = {
            type: rollbackReason ? 'appointment.statusRolledBack' : 'appointment.statusUpdated',
            id: appt.id,
            previousStatus: current.status,
            newStatus: status,
            patientId: current.patientId,
            doctorId: current.doctorId,
            userId: req.user?.id,
            userRole: req.user?.role,
            rollbackReason,
            ts: new Date().toISOString()
        };

        publishEvent(EXCHANGE, eventData.type, eventData).catch(() => { });

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

        // If user is a Doctor, they can only edit their own appointments
        if (req.user && req.user.role === 'DOCTOR' && req.user.id !== existing.doctorId) {
            return res.status(403).json({ error: 'Doctors can only edit their own appointments' });
        }

        // Check overlap for same doctor (excluding current appointment) only against active statuses
        const overlap = await prisma.appointment.findFirst({
            where: {
                doctorId,
                startTime: { lt: end },
                endTime: { gt: start },
                id: { not: id },
                status: { in: ['SCHEDULED', 'CONFIRMED'] }
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