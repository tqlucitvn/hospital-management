const { PrismaClient } = require('@prisma/client');
const prisma = new PrismaClient();
const { publishEvent } = require('../lib/broker');

const EXCHANGE = 'appointment.events'; // dùng 1 exchange


exports.create = async (req, res) => {
    try {
        const { patientId, doctorId, startTime, endTime, reason } = req.body;
        if (!patientId || !doctorId || !startTime || !endTime)
            return res.status(400).json({ error: 'Missing fields' });
        const appt = await prisma.appointment.create({
            data: { patientId, doctorId, startTime: new Date(startTime), endTime: new Date(endTime), reason }
        });
        publishEvent(EXCHANGE, 'appointment.created', { id: appt.id, patientId, doctorId, startTime, endTime }).catch(() => { });
        res.status(201).json(appt);
    } catch (e) {
        res.status(500).json({ error: 'Create failed', details: e.message });
    }
};

exports.list = async (_req, res) => {
    try {
        const rows = await prisma.appointment.findMany({ orderBy: { startTime: 'asc' } });
        res.json(rows);
    } catch (e) { res.status(500).json({ error: 'List failed' }); }
};

exports.updateStatus = async (req, res) => {
    try {
        const { id } = req.params;
        const { status } = req.body;
        const appt = await prisma.appointment.update({ where: { id }, data: { status } });
        publishEvent(EXCHANGE, 'appointment.statusUpdated', { id, status }).catch(() => { });
        res.json(appt);
    } catch (e) {
        if (e.code === 'P2025') return res.status(404).json({ error: 'Not found' });
        res.status(500).json({ error: 'Update failed' });
    }
};

exports.delete = async (req, res) => {
    try {
        const { id } = req.params;
        await prisma.appointment.delete({ where: { id } });
        publishEvent(EXCHANGE, 'appointment.deleted', { id }).catch(() => { });
        res.status(204).send();
    } catch (e) {
        if (e.code === 'P2025') return res.status(404).json({ error: 'Not found' });
        res.status(500).json({ error: 'Delete failed' });
    }
};