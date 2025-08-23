const express = require('express');
const router = express.Router();
const auth = require('../middleware/auth');
const { PrismaClient } = require('@prisma/client');
const prisma = new PrismaClient();
const patientController = require('../controllers/patient.controller');

// Basic stats endpoint
router.get('/stats', auth(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']), patientController.getStats);

router.post('/', auth(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']), patientController.createPatient);
router.get('/', auth(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']), patientController.getAllPatients);
router.get('/:id', auth(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']), patientController.getPatientById);
router.put('/:id', auth(['ADMIN', 'DOCTOR', 'NURSE']), patientController.updatePatient);
router.delete('/:id', auth(['ADMIN']), patientController.deletePatient);

// Thống kê bệnh nhân theo tháng (ADMIN)
router.get('/stats/monthly', auth(['ADMIN']), async (req, res) => {
  try {
    const year = parseInt(req.query.year, 10) || new Date().getFullYear();
    const start = new Date(Date.UTC(year, 0, 1, 0, 0, 0));
    const end = new Date(Date.UTC(year + 1, 0, 1, 0, 0, 0));

    const rows = await prisma.$queryRaw`
      SELECT to_char(date_trunc('month', "createdAt"), 'YYYY-MM') AS month, count(*)::int AS total
      FROM "Patient"
      WHERE "createdAt" >= ${start} AND "createdAt" < ${end}
      GROUP BY 1
      ORDER BY 1
    `;

    const pad = Array.from({ length: 12 }, (_, i) => {
      const m = `${year}-${String(i + 1).padStart(2, '0')}`;
      const found = rows.find(r => r.month === m);
      return { month: m, total: found ? Number(found.total) : 0 };
    });

    res.json(pad);
  } catch (e) {
    res.status(500).json({ error: 'Stats failed' });
  }
});

// Patient history: appointments + prescriptions
router.get('/:patientId/history', auth(['ADMIN', 'DOCTOR', 'NURSE']), async (req, res) => {
  try {
    const { patientId } = req.params;
    const patient = await prisma.patient.findUnique({ where: { id: patientId } });
    if (!patient) return res.status(404).json({ error: 'Patient not found' });

    const authHeader = req.headers.authorization || '';
    // Gọi services khác trong mạng docker theo service name
    const [apptsResp, presResp] = await Promise.all([
      fetch('http://appointment-service:3003/api/appointments', { headers: { Authorization: authHeader } }),
      fetch(`http://prescription-service:3005/api/prescriptions?patientId=${patientId}`, { headers: { Authorization: authHeader } })
    ]);

    const allAppts = await apptsResp.json();
    const appointments = Array.isArray(allAppts) ? allAppts.filter(a => a.patientId === patientId) : [];
    const prescriptions = await presResp.json();

    res.json({ patient, appointments, prescriptions });
  } catch (e) {
    res.status(500).json({ error: 'Load history failed' });
  }
});

module.exports = router;