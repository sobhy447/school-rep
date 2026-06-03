const express = require('express');
const router = express.Router();
const { protect, authorize } = require('../middleware/auth');
const {
  getExcuses,
  addExcuse,
  deleteExcuse,
  getClassStudents,
  getAllClasses,
  getAllSubjects
} = require('../controllers/excuseController');

// Excuse routes
router.get('/', protect, getExcuses);
router.post('/', protect, authorize('admin', 'head', 'teacher'), addExcuse);
router.delete('/:id', protect, authorize('admin', 'head'), deleteExcuse);

// Class routes
router.get('/classes', protect, getAllClasses);
router.get('/classes/:id/students', protect, getClassStudents);

// Subject routes
router.get('/subjects', protect, getAllSubjects);

module.exports = router;
