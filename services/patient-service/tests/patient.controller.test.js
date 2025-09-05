const request = require('supertest');
const express = require('express');
const { createPatient, getAllPatients, getPatientById, updatePatient, deletePatient, searchPatients } = require('../src/controllers/patient.controller');
const prisma = require('../src/lib/prisma');

jest.mock('../src/lib/prisma');

const app = express();
app.use(express.json());
app.post('/patients', createPatient);
app.get('/patients', getAllPatients);
app.get('/patients/search', searchPatients);
app.get('/patients/:id', getPatientById);
app.put('/patients/:id', updatePatient);
app.delete('/patients/:id', deletePatient);

describe('Patient Controller', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('POST /patients', () => {
    it('should create patient successfully', async () => {
      const patientData = {
        fullName: 'Nguyen Van A',
        dateOfBirth: '1990-01-01',
        gender: 'MALE',
        phoneNumber: '0123456789',
        email: 'patient@example.com',
        address: '123 Main St',
        emergencyContact: 'Nguyen Van B',
        emergencyPhone: '0987654321'
      };

      const mockCreatedPatient = {
        id: '1',
        ...patientData,
        dateOfBirth: new Date('1990-01-01'),
        createdAt: new Date(),
        updatedAt: new Date()
      };

      prisma.patient.create.mockResolvedValue(mockCreatedPatient);

      const response = await request(app)
        .post('/patients')
        .send(patientData);

      expect(response.status).toBe(201);
      expect(response.body.fullName).toBe(patientData.fullName);
      expect(response.body.email).toBe(patientData.email);
      expect(prisma.patient.create).toHaveBeenCalledWith({
        data: expect.objectContaining({
          fullName: patientData.fullName,
          email: patientData.email
        })
      });
    });

    it('should fail with missing required fields', async () => {
      const response = await request(app)
        .post('/patients')
        .send({
          fullName: 'Nguyen Van A'
          // Missing other required fields
        });

      expect(response.status).toBe(400);
      expect(response.body.message).toContain('Missing required fields');
    });

    it('should handle database errors', async () => {
      const patientData = {
        fullName: 'Nguyen Van A',
        dateOfBirth: '1990-01-01',
        gender: 'MALE',
        phoneNumber: '0123456789'
      };

      prisma.patient.create.mockRejectedValue(new Error('Database error'));

      const response = await request(app)
        .post('/patients')
        .send(patientData);

      expect(response.status).toBe(500);
      expect(response.body.message).toBe('Error creating patient');
    });
  });

  describe('GET /patients', () => {
    it('should return all patients', async () => {
      const mockPatients = [
        {
          id: '1',
          fullName: 'Nguyen Van A',
          dateOfBirth: new Date('1990-01-01'),
          gender: 'MALE',
          phoneNumber: '0123456789'
        },
        {
          id: '2',
          fullName: 'Nguyen Thi B',
          dateOfBirth: new Date('1992-05-15'),
          gender: 'FEMALE',
          phoneNumber: '0987654321'
        }
      ];

      prisma.patient.findMany.mockResolvedValue(mockPatients);

      const response = await request(app).get('/patients');

      expect(response.status).toBe(200);
      expect(response.body).toHaveLength(2);
      expect(response.body[0].fullName).toBe('Nguyen Van A');
      expect(prisma.patient.findMany).toHaveBeenCalledWith({
        orderBy: { createdAt: 'desc' }
      });
    });
  });

  describe('GET /patients/search', () => {
    it('should search patients by name', async () => {
      const mockPatients = [
        {
          id: '1',
          fullName: 'Nguyen Van A',
          phoneNumber: '0123456789'
        }
      ];

      prisma.patient.findMany.mockResolvedValue(mockPatients);

      const response = await request(app)
        .get('/patients/search')
        .query({ q: 'Nguyen' });

      expect(response.status).toBe(200);
      expect(response.body).toHaveLength(1);
      expect(prisma.patient.findMany).toHaveBeenCalledWith({
        where: {
          OR: [
            { fullName: { contains: 'Nguyen', mode: 'insensitive' } },
            { phoneNumber: { contains: 'Nguyen' } },
            { email: { contains: 'Nguyen', mode: 'insensitive' } }
          ]
        },
        orderBy: { createdAt: 'desc' }
      });
    });

    it('should return empty array when no matches found', async () => {
      prisma.patient.findMany.mockResolvedValue([]);

      const response = await request(app)
        .get('/patients/search')
        .query({ q: 'nonexistent' });

      expect(response.status).toBe(200);
      expect(response.body).toHaveLength(0);
    });
  });

  describe('GET /patients/:id', () => {
    it('should return patient by id', async () => {
      const mockPatient = {
        id: '1',
        fullName: 'Nguyen Van A',
        dateOfBirth: new Date('1990-01-01'),
        gender: 'MALE',
        phoneNumber: '0123456789'
      };

      prisma.patient.findUnique.mockResolvedValue(mockPatient);

      const response = await request(app).get('/patients/1');

      expect(response.status).toBe(200);
      expect(response.body.fullName).toBe('Nguyen Van A');
      expect(prisma.patient.findUnique).toHaveBeenCalledWith({
        where: { id: '1' }
      });
    });

    it('should return 404 for non-existent patient', async () => {
      prisma.patient.findUnique.mockResolvedValue(null);

      const response = await request(app).get('/patients/999');

      expect(response.status).toBe(404);
      expect(response.body.message).toBe('Patient not found');
    });
  });

  describe('PUT /patients/:id', () => {
    it('should update patient successfully', async () => {
      const updateData = {
        fullName: 'Nguyen Van A Updated',
        phoneNumber: '0999888777'
      };

      const mockUpdatedPatient = {
        id: '1',
        fullName: 'Nguyen Van A Updated',
        phoneNumber: '0999888777',
        dateOfBirth: new Date('1990-01-01'),
        gender: 'MALE'
      };

      prisma.patient.update.mockResolvedValue(mockUpdatedPatient);

      const response = await request(app)
        .put('/patients/1')
        .send(updateData);

      expect(response.status).toBe(200);
      expect(response.body.fullName).toBe('Nguyen Van A Updated');
      expect(prisma.patient.update).toHaveBeenCalledWith({
        where: { id: '1' },
        data: updateData
      });
    });
  });

  describe('DELETE /patients/:id', () => {
    it('should delete patient successfully', async () => {
      prisma.patient.delete.mockResolvedValue({ id: '1' });

      const response = await request(app).delete('/patients/1');

      expect(response.status).toBe(200);
      expect(response.body.message).toBe('Patient deleted successfully');
      expect(prisma.patient.delete).toHaveBeenCalledWith({
        where: { id: '1' }
      });
    });
  });
});
