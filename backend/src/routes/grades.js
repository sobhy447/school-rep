const express = require('express');
const router = express.Router();
const { protect, authorize } = require('../middleware/auth');
const {
  getTeacherClasses,
  getClassGrades,
  saveGrades,
  submitGrades,
  getHeadDepartmentClasses,
  getTeacherGradesForReview,
  editGradeAsHead,
  approveDepartment,
  getAllGrades,
  getPrintData,
  unlockApproval
} = require('../controllers/gradeController');

// Teacher routes
router.get('/teacher/classes', protect, authorize('teacher', 'head', 'admin'), getTeacherClasses);
router.get('/class/:classId/subject/:subjectId', protect, getClassGrades);
router.post('/save', protect, authorize('teacher', 'head', 'admin'), saveGrades);
router.post('/submit', protect, authorize('teacher', 'head', 'admin'), submitGrades);

// Head of department routes
router.get('/head/department-classes', protect, authorize('head', 'admin'), getHeadDepartmentClasses);
router.get('/head/review/:classId/:teacherId/:subjectId', protect, authorize('head', 'admin'), getTeacherGradesForReview);
router.post('/head/edit', protect, authorize('head', 'admin'), editGradeAsHead);
router.post('/head/approve-department', protect, authorize('head', 'admin'), approveDepartment);

// Control routes
router.get('/control/all', protect, authorize('control', 'admin'), getAllGrades);
router.get('/control/print', protect, authorize('control', 'admin'), getPrintData);

// Admin routes
router.post('/admin/unlock', protect, authorize('admin'), unlockApproval);

module.exports = router;
