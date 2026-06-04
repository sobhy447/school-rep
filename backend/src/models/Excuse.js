const mongoose = require('mongoose');

const ExcuseSchema = new mongoose.Schema({
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
  type: {
    type: String,
    enum: ['classwork', 'exam', 'both'],
    required: true
  },
  reason: {
    type: String,
    required: [true, 'Please add excuse reason']
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

module.exports = mongoose.model('Excuse', ExcuseSchema);
