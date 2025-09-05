const request = require('supertest');
const express = require('express');
const { getAllUsers, getUserById, updateUser, deleteUser } = require('../src/controllers/user.controller');
const prisma = require('../src/lib/prisma');
const { authenticateToken, requireRole } = require('../src/middleware/auth');

jest.mock('../src/lib/prisma');
jest.mock('../src/middleware/auth');

const app = express();
app.use(express.json());

// Mock middleware
authenticateToken.mockImplementation((req, res, next) => {
  req.user = { id: '1', role: 'ADMIN' };
  next();
});

requireRole.mockImplementation(() => (req, res, next) => next());

app.get('/users', authenticateToken, requireRole(['ADMIN']), getAllUsers);
app.get('/users/:id', authenticateToken, getUserById);
app.put('/users/:id', authenticateToken, requireRole(['ADMIN']), updateUser);
app.delete('/users/:id', authenticateToken, requireRole(['ADMIN']), deleteUser);

describe('User Controller', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('GET /users', () => {
    it('should return all users', async () => {
      const mockUsers = [
        {
          id: '1',
          email: 'admin@hospital.com',
          fullName: 'Admin User',
          role: 'ADMIN',
          isActive: true
        },
        {
          id: '2',
          email: 'doctor@hospital.com',
          fullName: 'Dr. John Doe',
          role: 'DOCTOR',
          isActive: true
        }
      ];

      prisma.user.findMany.mockResolvedValue(mockUsers);

      const response = await request(app).get('/users');

      expect(response.status).toBe(200);
      expect(response.body).toHaveLength(2);
      expect(response.body[0].email).toBe('admin@hospital.com');
      expect(prisma.user.findMany).toHaveBeenCalledWith({
        select: {
          id: true,
          email: true,
          fullName: true,
          role: true,
          phoneNumber: true,
          isActive: true,
          createdAt: true,
          updatedAt: true
        },
        orderBy: { createdAt: 'desc' }
      });
    });

    it('should handle database errors', async () => {
      prisma.user.findMany.mockRejectedValue(new Error('Database error'));

      const response = await request(app).get('/users');

      expect(response.status).toBe(500);
      expect(response.body.message).toBe('Error fetching users');
    });
  });

  describe('GET /users/:id', () => {
    it('should return user by id', async () => {
      const mockUser = {
        id: '1',
        email: 'doctor@hospital.com',
        fullName: 'Dr. John Doe',
        role: 'DOCTOR',
        isActive: true
      };

      prisma.user.findUnique.mockResolvedValue(mockUser);

      const response = await request(app).get('/users/1');

      expect(response.status).toBe(200);
      expect(response.body.email).toBe('doctor@hospital.com');
      expect(prisma.user.findUnique).toHaveBeenCalledWith({
        where: { id: '1' },
        select: {
          id: true,
          email: true,
          fullName: true,
          role: true,
          phoneNumber: true,
          isActive: true,
          createdAt: true,
          updatedAt: true
        }
      });
    });

    it('should return 404 for non-existent user', async () => {
      prisma.user.findUnique.mockResolvedValue(null);

      const response = await request(app).get('/users/999');

      expect(response.status).toBe(404);
      expect(response.body.message).toBe('User not found');
    });
  });

  describe('PUT /users/:id', () => {
    it('should update user successfully', async () => {
      const updateData = {
        fullName: 'Dr. John Updated',
        phoneNumber: '0987654321'
      };

      const mockUpdatedUser = {
        id: '1',
        email: 'doctor@hospital.com',
        fullName: 'Dr. John Updated',
        role: 'DOCTOR',
        phoneNumber: '0987654321',
        isActive: true
      };

      prisma.user.update.mockResolvedValue(mockUpdatedUser);

      const response = await request(app)
        .put('/users/1')
        .send(updateData);

      expect(response.status).toBe(200);
      expect(response.body.fullName).toBe('Dr. John Updated');
      expect(prisma.user.update).toHaveBeenCalledWith({
        where: { id: '1' },
        data: updateData,
        select: {
          id: true,
          email: true,
          fullName: true,
          role: true,
          phoneNumber: true,
          isActive: true,
          createdAt: true,
          updatedAt: true
        }
      });
    });

    it('should handle update errors', async () => {
      prisma.user.update.mockRejectedValue(new Error('User not found'));

      const response = await request(app)
        .put('/users/999')
        .send({ fullName: 'New Name' });

      expect(response.status).toBe(500);
      expect(response.body.message).toBe('Error updating user');
    });
  });

  describe('DELETE /users/:id', () => {
    it('should delete user successfully', async () => {
      prisma.user.delete.mockResolvedValue({ id: '1' });

      const response = await request(app).delete('/users/1');

      expect(response.status).toBe(200);
      expect(response.body.message).toBe('User deleted successfully');
      expect(prisma.user.delete).toHaveBeenCalledWith({
        where: { id: '1' }
      });
    });

    it('should handle deletion errors', async () => {
      prisma.user.delete.mockRejectedValue(new Error('User not found'));

      const response = await request(app).delete('/users/999');

      expect(response.status).toBe(500);
      expect(response.body.message).toBe('Error deleting user');
    });
  });
});
