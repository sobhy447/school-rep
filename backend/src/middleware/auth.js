const jwt = require('jsonwebtoken');
const User = require('../models/User');
const ErrorResponse = require('../utils/ErrorResponse');

// Protect routes
exports.protect = async (req, res, next) => {
  let token;

  if (req.headers.authorization && req.headers.authorization.startsWith('Bearer')) {
    token = req.headers.authorization.split(' ')[1];
  }

  if (!token) {
    return next(new ErrorResponse('غير مصرح لك بالوصول', 401));
  }

  try {
    const decoded = jwt.verify(token, process.env.JWT_SECRET);
    req.user = await User.findById(decoded.id);

    if (!req.user) {
      return next(new ErrorResponse('المستخدم غير موجود', 404));
    }

    if (!req.user.isActive) {
      return next(new ErrorResponse('الحساب معطل', 401));
    }

    next();
  } catch (err) {
    return next(new ErrorResponse('غير مصرح لك بالوصول', 401));
  }
};

// Authorize by role
exports.authorize = (...roles) => {
  return (req, res, next) => {
    if (!roles.includes(req.user.role)) {
      return next(new ErrorResponse('غير مصرح لك بهذا الدور', 403));
    }
    next();
  };
};
