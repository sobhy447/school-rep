const mongoose = require('mongoose');

const settingsSchema = new mongoose.Schema({
  // Period settings
  firstPeriodEnabled: {
    type: Boolean,
    default: true
  },
  secondPeriodEnabled: {
    type: Boolean,
    default: true
  },

  // Column settings
  workEnabled: {
    type: Boolean,
    default: true
  },
  examEnabled: {
    type: Boolean,
    default: true
  },

  // School info
  schoolName: {
    type: String,
    default: 'مدرسة النموذجية'
  },
  ministryName: {
    type: String,
    default: 'وزارة التربية'
  },
  country: {
    type: String,
    default: 'دولة الكويت'
  },
  academicYear: {
    type: String,
    default: '2025-2026'
  },

  // Grade limits
  workMax: {
    type: Number,
    default: 40
  },
  examMax: {
    type: Number,
    default: 60
  },
  allowHalf: {
    type: Boolean,
    default: true
  },

  createdAt: {
    type: Date,
    default: Date.now
  },
  updatedAt: {
    type: Date,
    default: Date.now
  }
});

settingsSchema.pre('save', function(next) {
  this.updatedAt = Date.now();
  next();
});

module.exports = mongoose.model('Settings', settingsSchema);
