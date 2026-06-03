const mongoose = require('mongoose');

const classSchema = new mongoose.Schema({
  name: {
    type: String,
    required: [true, 'اسم الفصل مطلوب'],
    trim: true
  },
  gradeLevel: {
    type: Number,
    required: [true, 'المرحلة الدراسية مطلوبة'],
    enum: [6, 7, 8, 9]
  },
  students: [{
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Student'
  }],
  subjects: [{
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Subject'
  }],
  teachers: [{
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User'
  }],
  academicYear: {
    type: String,
    default: () => new Date().getFullYear().toString()
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
classSchema.index({ gradeLevel: 1 });
classSchema.index({ name: 1 });

module.exports = mongoose.model('Class', classSchema);
