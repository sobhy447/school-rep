const mongoose = require('mongoose');

const GradeSchema = new mongoose.Schema({
  student: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Student',
    required: true
  },
  subject: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Subject',
    required: true
  },
  teacher: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: true
  },
  classId: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Class',
    required: true
  },
  period: {
    type: String,
    enum: ['1', '2'],
    required: true
  },
  classwork: {
    type: Number,
    min: 0,
    max: 40,
    default: null
  },
  exam: {
    type: Number,
    min: 0,
    max: 60,
    default: null
  },
  total: {
    type: Number,
    min: 0,
    max: 100,
    default: null
  },
  isExcused: {
    type: Boolean,
    default: false
  },
  excuseReason: {
    type: String,
    default: ''
  },
  status: {
    type: String,
    enum: ['draft', 'submitted', 'approved', 'locked'],
    default: 'draft'
  },
  submittedAt: {
    type: Date,
    default: null
  },
  approvedAt: {
    type: Date,
    default: null
  },
  approvedBy: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    default: null
  },
  notes: {
    type: String,
    default: ''
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

// Calculate total before saving
GradeSchema.pre('save', function(next) {
  if (this.classwork !== null && this.exam !== null) {
    this.total = this.classwork + this.exam;
  }
  this.updatedAt = Date.now();
  next();
});

// Index for faster queries
GradeSchema.index({ student: 1, subject: 1, period: 1 });
GradeSchema.index({ teacher: 1, classId: 1, period: 1 });

module.exports = mongoose.model('Grade', GradeSchema);
