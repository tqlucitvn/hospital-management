const express = require('express');
const router = express.Router();
const c = require('../controllers/prescription.controller');
const auth = require('../middleware/auth');
const { PrismaClient } = require('@prisma/client'); // thêm
const prisma = new PrismaClient(); // thêm

router.post('/', auth(['DOCTOR', 'ADMIN']), c.create);
router.get('/', auth(['DOCTOR', 'NURSE', 'ADMIN']), c.list);

// Thêm API thống kê theo tháng (đặt TRƯỚC '/:id')
router.get('/stats/monthly', auth(['ADMIN']), async (req, res) => {
  try {
    const year = parseInt(req.query.year, 10) || new Date().getFullYear();
    const status = (req.query.status || 'FILLED').toUpperCase();
    const start = new Date(Date.UTC(year, 0, 1, 0, 0, 0));
    const end = new Date(Date.UTC(year + 1, 0, 1, 0, 0, 0));

    const rows = await prisma.$queryRaw`
      SELECT to_char(date_trunc('month', "createdAt"), 'YYYY-MM') AS month, count(*)::int AS total
      FROM "Prescription"
      WHERE "createdAt" >= ${start} AND "createdAt" < ${end}
        AND "status" = ${status}
      GROUP BY 1
      ORDER BY 1
    `;

    // Fill đủ 12 tháng
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

router.get('/:id', auth(['DOCTOR', 'NURSE', 'ADMIN']), c.getOne);
router.put('/:id', auth(['DOCTOR', 'ADMIN']), c.update);
router.patch('/:id/status', auth(['DOCTOR', 'ADMIN']), c.updateStatus);
router.delete('/:id', auth(['ADMIN']), c.delete);

module.exports = router;