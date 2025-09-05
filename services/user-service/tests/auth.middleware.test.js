const jwt = require('jsonwebtoken');
const { authenticateToken, requireRole } = require('../src/middleware/auth');

jest.mock('jsonwebtoken');

describe('Auth Middleware', () => {
  let req, res, next;

  beforeEach(() => {
    req = {
      headers: {},
      user: null
    };
    res = {
      status: jest.fn().mockReturnThis(),
      json: jest.fn()
    };
    next = jest.fn();
    jest.clearAllMocks();
  });

  describe('authenticateToken', () => {
    it('should authenticate valid token', () => {
      const mockUser = { id: '1', email: 'test@hospital.com', role: 'DOCTOR' };
      req.headers.authorization = 'Bearer valid-token';
      jwt.verify.mockReturnValue(mockUser);

      authenticateToken(req, res, next);

      expect(req.user).toEqual(mockUser);
      expect(next).toHaveBeenCalled();
      expect(jwt.verify).toHaveBeenCalledWith('valid-token', process.env.JWT_SECRET);
    });

    it('should reject request without token', () => {
      authenticateToken(req, res, next);

      expect(res.status).toHaveBeenCalledWith(401);
      expect(res.json).toHaveBeenCalledWith({ message: 'Access token required' });
      expect(next).not.toHaveBeenCalled();
    });

    it('should reject request with invalid token format', () => {
      req.headers.authorization = 'InvalidFormat token';

      authenticateToken(req, res, next);

      expect(res.status).toHaveBeenCalledWith(401);
      expect(res.json).toHaveBeenCalledWith({ message: 'Access token required' });
      expect(next).not.toHaveBeenCalled();
    });

    it('should reject request with invalid token', () => {
      req.headers.authorization = 'Bearer invalid-token';
      jwt.verify.mockImplementation(() => {
        throw new Error('Invalid token');
      });

      authenticateToken(req, res, next);

      expect(res.status).toHaveBeenCalledWith(403);
      expect(res.json).toHaveBeenCalledWith({ message: 'Invalid or expired token' });
      expect(next).not.toHaveBeenCalled();
    });
  });

  describe('requireRole', () => {
    it('should allow user with correct role', () => {
      req.user = { id: '1', role: 'ADMIN' };
      const middleware = requireRole(['ADMIN', 'DOCTOR']);

      middleware(req, res, next);

      expect(next).toHaveBeenCalled();
      expect(res.status).not.toHaveBeenCalled();
    });

    it('should allow user with one of multiple allowed roles', () => {
      req.user = { id: '1', role: 'DOCTOR' };
      const middleware = requireRole(['ADMIN', 'DOCTOR']);

      middleware(req, res, next);

      expect(next).toHaveBeenCalled();
      expect(res.status).not.toHaveBeenCalled();
    });

    it('should reject user with incorrect role', () => {
      req.user = { id: '1', role: 'NURSE' };
      const middleware = requireRole(['ADMIN']);

      middleware(req, res, next);

      expect(res.status).toHaveBeenCalledWith(403);
      expect(res.json).toHaveBeenCalledWith({ message: 'Insufficient permissions' });
      expect(next).not.toHaveBeenCalled();
    });

    it('should reject request without user in request', () => {
      const middleware = requireRole(['ADMIN']);

      middleware(req, res, next);

      expect(res.status).toHaveBeenCalledWith(403);
      expect(res.json).toHaveBeenCalledWith({ message: 'Insufficient permissions' });
      expect(next).not.toHaveBeenCalled();
    });
  });
});
