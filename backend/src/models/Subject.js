const mongoose = require('mongoose');

const SubjectSchema = new mongoose.Schema({
  name: {
    type: String,
    required: [true, 'Please add subject name'],
    unique: true,
    trim: true
  },
  code: {
    type: String,
    required: true,
    unique: true
  },
  department: {
    type: String,
    required: true
  },
  maxClasswork: {
    type: Number,
    default: 40
  },
  maxExam: {
    type: Number,
    default: 60
  },
  isActive: {
    type: Boolean,
    default: true
  }
});

module.exports = mongoose.model('Subject', SubjectSchema);
