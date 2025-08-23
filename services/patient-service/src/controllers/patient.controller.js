const { PrismaClient } = require('@prisma/client');
const prisma = new PrismaClient();

// Tạo bệnh nhân mới
exports.createPatient = async (req, res) => {
  try {
    const { 
      fullName, 
      email,
      phone,
      dateOfBirth, 
      gender, 
      address, 
      emergencyContact,
      medicalHistory
    } = req.body;
    
    const newPatient = await prisma.patient.create({
      data: {
        fullName,
        email,
        phone,
        dateOfBirth: new Date(dateOfBirth),
        gender,
        address,
        emergencyContact,
        medicalHistory
      },
    });
    res.status(201).json(newPatient);
  } catch (error) {
    if (error.code === 'P2002') {
      return res.status(400).json({ error: 'Email or phone already exists.' });
    }
    res.status(500).json({ error: 'Could not create patient.', details: error.message });
  }
};

// Lấy tất cả bệnh nhân với pagination và search
exports.getAllPatients = async (req, res) => {
  try {
    const { page = 1, limit = 10, search = '' } = req.query;
    const offset = (parseInt(page) - 1) * parseInt(limit);
    
    // Build where clause for search
    const whereClause = search ? {
      OR: [
        { fullName: { contains: search, mode: 'insensitive' } },
        { email: { contains: search, mode: 'insensitive' } },
        { phone: { contains: search, mode: 'insensitive' } }
      ]
    } : {};
    
    // Get total count
    const total = await prisma.patient.count({ where: whereClause });
    
    // Get patients
    const patients = await prisma.patient.findMany({
      where: whereClause,
      skip: offset,
      take: parseInt(limit),
      orderBy: { createdAt: 'desc' }
    });
    
    res.status(200).json({
      patients,
      total,
      page: parseInt(page),
      limit: parseInt(limit),
      totalPages: Math.ceil(total / parseInt(limit))
    });
  } catch (error) {
    res.status(500).json({ error: 'Could not fetch patients.', details: error.message });
  }
};

// Lấy thống kê cơ bản
exports.getStats = async (req, res) => {
  try {
    const total = await prisma.patient.count();
    
    // Count patients created today
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    const today_count = await prisma.patient.count({
      where: {
        createdAt: {
          gte: today,
          lt: tomorrow
        }
      }
    });
    
    res.status(200).json({
      total,
      today: today_count
    });
  } catch (error) {
    res.status(500).json({ error: 'Could not fetch stats.', details: error.message });
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
    const updateData = { ...req.body };
    
    // Convert dateOfBirth if provided
    if (updateData.dateOfBirth) {
      updateData.dateOfBirth = new Date(updateData.dateOfBirth);
    }
    
    const patient = await prisma.patient.update({
      where: { id },
      data: updateData,
    });
    res.status(200).json(patient);
  } catch (error) {
    if (error.code === 'P2025') {
        return res.status(404).json({ error: 'Patient not found.' });
    }
    if (error.code === 'P2002') {
      return res.status(400).json({ error: 'Email or phone already exists.' });
    }
    res.status(500).json({ error: 'Could not update patient.', details: error.message });
  }
};

// Xóa bệnh nhân
exports.deletePatient = async (req, res) => {
  try {
    const { id } = req.params;
    await prisma.patient.delete({ where: { id } });
    res.status(200).json({ message: 'Patient deleted successfully.' });
  } catch (error) {
    if (error.code === 'P2025') {
        return res.status(404).json({ error: 'Patient not found.' });
    }
    res.status(500).json({ error: 'Could not delete patient.', details: error.message });
  }
};