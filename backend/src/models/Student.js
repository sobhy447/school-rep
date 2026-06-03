const mongoose = require('mongoose');

const studentSchema = new mongoose.Schema({
  name: {
    type: String,
    required: [true, 'اسم الطالب مطلوب'],
    trim: true
  },
  studentId: {
    type: String,
    required: [true, 'رقم الطالب مطلوب'],
    unique: true,
    trim: true
  },
  class: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Class',
    required: [true, 'الفصل مطلوب']
  },
  gradeLevel: {
    type: Number,
    required: [true, 'المرحلة الدراسية مطلوبة'],
    enum: [6, 7, 8, 9]
  },
  dateOfBirth: {
    type: Date
  },
  gender: {
    type: String,
    enum: ['male', 'female']
  },
  parentPhone: {
    type: String,
    trim: true
  },
  parentEmail: {
    type: String,
    trim: true,
    lowercase: true
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
studentSchema.index({ studentId: 1 });
studentSchema.index({ class: 1 });
studentSchema.index({ gradeLevel: 1 });

module.exports = mongoose.model('Student', studentSchema);
