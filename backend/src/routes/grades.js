const express = require('express');
const router = express.Router();
const { protect, authorize } = require('../middleware/auth');
const {
  getTeacherClasses,
  getClassGrades,
  saveGrades,
  submitGrades,
  getHeadDepartmentClasses,
  headReviewGrades,
  headEditGrade,
  headApproveDepartment,
  getControlGrades,
  getPrintData
} = require('../controllers/gradeController');

router.get('/teacher/classes', protect, authorize('teacher', 'head', 'admin'), getTeacherClasses);
router.get('/class/:id/subject/:subjectId', protect, authorize('teacher', 'head', 'admin'), getClassGrades);
router.post('/save', protect, authorize('teacher', 'head', 'admin'), saveGrades);
router.post('/submit', protect, authorize('teacher', 'head', 'admin'), submitGrades);

router.get('/head/department-classes', protect, authorize('head', 'admin'), getHeadDepartmentClasses);
router.get('/head/review/:classId/:teacherId/:subjectId', protect, authorize('head', 'admin'), headReviewGrades);
router.post('/head/edit', protect, authorize('head', 'admin'), headEditGrade);
router.post('/head/approve-department', protect, authorize('head', 'admin'), headApproveDepartment);

router.get('/control/all', protect, authorize('control', 'admin'), getControlGrades);
router.get('/control/print', protect, authorize('control', 'admin'), getPrintData);

module.exports = router;
