const { PrismaClient } = require('@prisma/client');
const prisma = new PrismaClient();

// Tạo bệnh nhân mới
exports.createPatient = async (req, res) => {
  try {
    const { fullName, dateOfBirth, gender, address, phoneNumber } = req.body;
    const newPatient = await prisma.patient.create({
      data: {
        fullName,
        dateOfBirth: new Date(dateOfBirth),
        gender,
        address,
        phoneNumber,
      },
    });
    res.status(201).json(newPatient);
  } catch (error) {
    res.status(500).json({ error: 'Could not create patient.', details: error.message });
  }
};

// Lấy tất cả bệnh nhân
exports.getAllPatients = async (req, res) => {
  try {
    const patients = await prisma.patient.findMany();
    res.status(200).json(patients);
  } catch (error) {
    res.status(500).json({ error: 'Could not fetch patients.', details: error.message });
  }
};

// Lấy bệnh nhân theo ID
exports.getPatientById = async (req, res) => {
  try {
    const { id } = req.params;
    const patient = await prisma.patient.findUnique({ where: { id } });
    if (!patient) {
      return res.status(404).json({ error: 'Patient not found.' });
    }
    res.status(200).json(patient);
  } catch (error) {
    res.status(500).json({ error: 'Could not fetch patient.', details: error.message });
  }
};

// Cập nhật thông tin bệnh nhân
exports.updatePatient = async (req, res) => {
  try {
    const { id } = req.params;
    const patient = await prisma.patient.update({
      where: { id },
      data: req.body,
    });
    res.status(200).json(patient);
  } catch (error) {
    if (error.code === 'P2025') {
        return res.status(404).json({ error: 'Patient not found.' });
    }
    res.status(500).json({ error: 'Could not update patient.', details: error.message });
  }
};

// Xóa bệnh nhân
exports.deletePatient = async (req, res) => {
  try {
    const { id } = req.params;
    await prisma.patient.delete({ where: { id } });
    res.status(204).send(); // 204 No Content
  } catch (error) {
    if (error.code === 'P2025') {
        return res.status(404).json({ error: 'Patient not found.' });
    }
    res.status(500).json({ error: 'Could not delete patient.', details: error.message });
  }
};