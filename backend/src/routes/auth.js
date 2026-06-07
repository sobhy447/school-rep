const express = require('express');
const router = express.Router();
const User = require('../models/User');
const asyncHandler = require('../utils/asyncHandler');
const ErrorResponse = require('../utils/ErrorResponse');
const { protect } = require('../middleware/auth');

// @desc    Login user
// @route   POST /api/auth/login
// @access  Public
router.post('/login', asyncHandler(async (req, res, next) => {
  const { civilId, password } = req.body;

  if (!civilId || !password) {
    return next(new ErrorResponse('Please provide civil ID and password', 400));
  }

  const user = await User.findOne({ civilId }).select('+password');

  if (!user) {
    return next(new ErrorResponse('Invalid credentials', 401));
  }

  const isMatch = await user.matchPassword(password);

  if (!isMatch) {
    return next(new ErrorResponse('Invalid credentials', 401));
  }

  const token = user.getSignedJwtToken();

  res.status(200).json({
    success: true,
    token,
    user: {
      id: user._id,
      name: user.name,
      email: user.email,
      role: user.role,
      department: user.department
    }
  });
}));

// @desc    Get current user
// @route   GET /api/auth/me
// @access  Private
router.get('/me', protect, asyncHandler(async (req, res, next) => {
  const user = await User.findById(req.user.id);

  res.status(200).json({
    success: true,
    data: user
  });
}));

module.exports = router;
