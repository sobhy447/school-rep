const mongoose = require('mongoose');

const subjectSchema = new mongoose.Schema({
  name: {
    type: String,
    required: [true, 'اسم المادة مطلوب'],
    trim: true
  },
  code: {
    type: String,
    required: [true, 'كود المادة مطلوب'],
    unique: true,
    trim: true
  },
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
  isActive: {
    type: Boolean,
    default: true
  },
  createdAt: {
    type: Date,
    default: Date.now
  }
});

// Indexes
subjectSchema.index({ code: 1 });

module.exports = mongoose.model('Subject', subjectSchema);
