const express = require('express');
const router = express.Router();
const jwt = require('jsonwebtoken');
const User = require('../models/User');
const asyncHandler = require('../utils/asyncHandler');
const ErrorResponse = require('../utils/ErrorResponse');

// @desc    Register user
// @route   POST /api/auth/register
// @access  Public
router.post('/register', asyncHandler(async (req, res, next) => {
  const { name, civilId, password, role, subject } = req.body;

  // Check if user exists
  const userExists = await User.findOne({ civilId });
  if (userExists) {
    return next(new ErrorResponse('هذا الرقم المدني مسجل مسبقاً', 400));
  }

  // Create user
  const user = await User.create({
    name,
    civilId,
    password,
    role: role || 'teacher',
    subject
  });

  sendTokenResponse(user, 201, res);
}));

// @desc    Login user
// @route   POST /api/auth/login
// @access  Public
router.post('/login', asyncHandler(async (req, res, next) => {
  const { civilId, password } = req.body;

  // Validate
  if (!civilId || !password) {
    return next(new ErrorResponse('الرقم المدني وكلمة المرور مطلوبة', 400));
  }

  // Check user
  const user = await User.findOne({ civilId }).select('+password');
  if (!user) {
    return next(new ErrorResponse('بيانات الدخول غير صحيحة', 401));
  }

  // Check password
  const isMatch = await user.comparePassword(password);
  if (!isMatch) {
    return next(new ErrorResponse('بيانات الدخول غير صحيحة', 401));
  }

  // Update last login
  user.lastLogin = new Date();
  await user.save();

  sendTokenResponse(user, 200, res);
}));

// @desc    Get current user
// @route   GET /api/auth/me
// @access  Private
router.get('/me', asyncHandler(async (req, res, next) => {
  const user = await User.findById(req.user.id).populate('subject', 'name');
  res.status(200).json({
    success: true,
    data: user
  });
}));

// @desc    Update password
// @route   PUT /api/auth/updatepassword
// @access  Private
router.put('/updatepassword', asyncHandler(async (req, res, next) => {
  const { currentPassword, newPassword } = req.body;

  const user = await User.findById(req.user.id).select('+password');

  // Check current password
  const isMatch = await user.comparePassword(currentPassword);
  if (!isMatch) {
    return next(new ErrorResponse('كلمة المرور الحالية غير صحيحة', 401));
  }

  user.password = newPassword;
  await user.save();

  sendTokenResponse(user, 200, res);
}));

// Helper: Send token response
const sendTokenResponse = (user, statusCode, res) => {
  const token = jwt.sign(
    { id: user._id },
    process.env.JWT_SECRET,
    { expiresIn: process.env.JWT_EXPIRE || '30d' }
  );

  res.status(statusCode).json({
    success: true,
    token,
    user: {
      id: user._id,
      name: user.name,
      civilId: user.civilId,
      role: user.role,
      subject: user.subject
    }
  });
};

module.exports = router;
