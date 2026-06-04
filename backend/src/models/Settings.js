const mongoose = require('mongoose');

const SettingsSchema = new mongoose.Schema({
  activePeriod: {
    type: String,
    enum: ['1', '2', 'both', 'none'],
    default: '1'
  },
  activeColumns: {
    classwork: {
      type: Boolean,
      default: true
    },
    exam: {
      type: Boolean,
      default: true
    }
  },
  schoolName: {
    type: String,
    default: 'School Name'
  },
  schoolLogo: {
    type: String,
    default: ''
  },
  academicYear: {
    type: String,
    default: '2025-2026'
  },
  updatedAt: {
    type: Date,
    default: Date.now
  }
});

module.exports = mongoose.model('Settings', SettingsSchema);
