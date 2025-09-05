const request = require('supertest');
const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const { login, register } = require('../src/controllers/auth.controller');
const prisma = require('../src/lib/prisma');

jest.mock('../src/lib/prisma');
jest.mock('bcryptjs');
jest.mock('jsonwebtoken');

const app = express();
app.use(express.json());
app.post('/auth/login', login);
app.post('/auth/register', register);

describe('Auth Controller', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('POST /auth/login', () => {
    it('should login successfully with valid credentials', async () => {
      const mockUser = {
        id: '1',
        email: 'doctor@hospital.com',
        password: 'hashedPassword',
        fullName: 'Dr. John Doe',
        role: 'DOCTOR',
        isActive: true
      };

      prisma.user.findUnique.mockResolvedValue(mockUser);
      bcrypt.compare.mockResolvedValue(true);
      jwt.sign.mockReturnValue('fake-jwt-token');

      const response = await request(app)
        .post('/auth/login')
        .send({
          email: 'doctor@hospital.com',
          password: 'password123'
        });

      expect(response.status).toBe(200);
      expect(response.body).toHaveProperty('token');
      expect(response.body).toHaveProperty('user');
      expect(response.body.user.email).toBe('doctor@hospital.com');
      expect(prisma.user.findUnique).toHaveBeenCalledWith({
        where: { email: 'doctor@hospital.com' }
      });
    });

    it('should fail with invalid email', async () => {
      prisma.user.findUnique.mockResolvedValue(null);

      const response = await request(app)
        .post('/auth/login')
        .send({
          email: 'invalid@email.com',
          password: 'password123'
        });

      expect(response.status).toBe(401);
      expect(response.body.message).toBe('Invalid credentials');
    });

    it('should fail with wrong password', async () => {
      const mockUser = {
        id: '1',
        email: 'doctor@hospital.com',
        password: 'hashedPassword',
        isActive: true
      };

      prisma.user.findUnique.mockResolvedValue(mockUser);
      bcrypt.compare.mockResolvedValue(false);

      const response = await request(app)
        .post('/auth/login')
        .send({
          email: 'doctor@hospital.com',
          password: 'wrongpassword'
        });

      expect(response.status).toBe(401);
      expect(response.body.message).toBe('Invalid credentials');
    });

    it('should fail with inactive user', async () => {
      const mockUser = {
        id: '1',
        email: 'doctor@hospital.com',
        password: 'hashedPassword',
        isActive: false
      };

      prisma.user.findUnique.mockResolvedValue(mockUser);

      const response = await request(app)
        .post('/auth/login')
        .send({
          email: 'doctor@hospital.com',
          password: 'password123'
        });

      expect(response.status).toBe(401);
      expect(response.body.message).toBe('Account is deactivated');
    });
  });

  describe('POST /auth/register', () => {
    it('should register new user successfully', async () => {
      const newUser = {
        email: 'nurse@hospital.com',
        password: 'password123',
        fullName: 'Jane Nurse',
        role: 'NURSE',
        phoneNumber: '0123456789'
      };

      const mockCreatedUser = {
        id: '2',
        ...newUser,
        password: 'hashedPassword',
        isActive: true,
        createdAt: new Date()
      };

      prisma.user.findUnique.mockResolvedValue(null); // Email not exists
      bcrypt.hash.mockResolvedValue('hashedPassword');
      prisma.user.create.mockResolvedValue(mockCreatedUser);

      const response = await request(app)
        .post('/auth/register')
        .send(newUser);

      expect(response.status).toBe(201);
      expect(response.body.message).toBe('User registered successfully');
      expect(response.body.user.email).toBe(newUser.email);
      expect(response.body.user).not.toHaveProperty('password');
    });

    it('should fail when email already exists', async () => {
      const existingUser = {
        id: '1',
        email: 'doctor@hospital.com'
      };

      prisma.user.findUnique.mockResolvedValue(existingUser);

      const response = await request(app)
        .post('/auth/register')
        .send({
          email: 'doctor@hospital.com',
          password: 'password123',
          fullName: 'Another Doctor',
          role: 'DOCTOR'
        });

      expect(response.status).toBe(400);
      expect(response.body.message).toBe('Email already exists');
    });

    it('should fail with invalid role', async () => {
      prisma.user.findUnique.mockResolvedValue(null);

      const response = await request(app)
        .post('/auth/register')
        .send({
          email: 'invalid@hospital.com',
          password: 'password123',
          fullName: 'Invalid User',
          role: 'INVALID_ROLE'
        });

      expect(response.status).toBe(400);
      expect(response.body.message).toContain('Invalid role');
    });
  });
});
