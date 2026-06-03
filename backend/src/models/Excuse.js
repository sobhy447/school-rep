const mongoose = require('mongoose');

const excuseSchema = new mongoose.Schema({
  student: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Student',
    required: [true, 'الطالب مطلوب']
  },
  class: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Class',
    required: [true, 'الفصل مطلوب']
  },
  subject: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Subject',
    required: [true, 'المادة مطلوبة']
  },
  period: {
    type: String,
    enum: ['first', 'second'],
    default: 'first'
  },
  type: {
    type: String,
    enum: ['work', 'exam'],
    required: [true, 'نوع العذر مطلوب (أعمال أو اختبار)']
  },
  reason: {
    type: String,
    required: [true, 'سبب العذر مطلوب']
  },
  academicYear: {
    type: Number,
    default: () => new Date().getFullYear()
  },
  createdBy: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: true
  },
  createdAt: {
    type: Date,
    default: Date.now
  }
});

// Indexes
excuseSchema.index({ student: 1, class: 1, subject: 1, period: 1, type: 1, academicYear: 1 }, { unique: true });
excuseSchema.index({ class: 1, subject: 1, period: 1, academicYear: 1 });

module.exports = mongoose.model('Excuse', excuseSchema);
