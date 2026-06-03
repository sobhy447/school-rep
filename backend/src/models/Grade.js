const mongoose = require('mongoose');

const gradeSchema = new mongoose.Schema({
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
  teacher: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: [true, 'المعلم مطلوب']
  },
  period: {
    type: String,
    enum: ['first', 'second'],
    default: 'first'
  },
  academicYear: {
    type: Number,
    default: () => new Date().getFullYear()
  },
  // Work grade (out of 40)
  work: {
    type: Number,
    min: [0, 'الدرجة لا يمكن أن تكون أقل من 0'],
    max: [40, 'درجة الأعمال لا يمكن أن تتجاوز 40']
  },
  workSaved: {
    type: Boolean,
    default: false
  },
  workSubmitted: {
    type: Boolean,
    default: false
  },
  workSubmittedAt: {
    type: Date
  },
  workEditedByHead: {
    type: Boolean,
    default: false
  },
  workEditedByHeadAt: {
    type: Date
  },
  // Exam grade (out of 60)
  exam: {
    type: Number,
    min: [0, 'الدرجة لا يمكن أن تكون أقل من 0'],
    max: [60, 'درجة الاختبار لا يمكن أن تتجاوز 60']
  },
  examSaved: {
    type: Boolean,
    default: false
  },
  examSubmitted: {
    type: Boolean,
    default: false
  },
  examSubmittedAt: {
    type: Date
  },
  examEditedByHead: {
    type: Boolean,
    default: false
  },
  examEditedByHeadAt: {
    type: Date
  },
  // Department approval
  workDeptApproved: {
    type: Boolean,
    default: false
  },
  workDeptApprovedAt: {
    type: Date
  },
  examDeptApproved: {
    type: Boolean,
    default: false
  },
  examDeptApprovedAt: {
    type: Date
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

// Indexes for performance
gradeSchema.index({ student: 1, class: 1, subject: 1, period: 1, academicYear: 1 }, { unique: true });
gradeSchema.index({ class: 1, subject: 1, period: 1, academicYear: 1 });
gradeSchema.index({ teacher: 1, academicYear: 1 });
gradeSchema.index({ workSubmitted: 1, examSubmitted: 1 });
gradeSchema.index({ workDeptApproved: 1, examDeptApproved: 1 });

// Update timestamp on save
gradeSchema.pre('save', function(next) {
  this.updatedAt = Date.now();
  next();
});

module.exports = mongoose.model('Grade', gradeSchema);
