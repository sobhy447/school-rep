import { useState, useCallback } from 'react';
import { gradeService } from '../services/gradeService';

export const useHeadGrades = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  // All classes in school with teachers of head's subject
  const [allClasses, setAllClasses] = useState([]);

  // Selected class for review
  const [selectedClass, setSelectedClass] = useState(null);
  const [selectedTeacher, setSelectedTeacher] = useState(null);
  const [selectedSubject, setSelectedSubject] = useState(null);

  // Grades data for review
  const [reviewData, setReviewData] = useState(null);

  // Head's own classes (as teacher)
  const [headClasses, setHeadClasses] = useState([]);

  // Approval status for department
  const [deptApprovalStatus, setDeptApprovalStatus] = useState({});
  const [canApproveDept, setCanApproveDept] = useState(false);

  // Load all classes with teachers of head's subject
  const loadAllClasses = useCallback(async () => {
    try {
      setLoading(true);
      const response = await gradeService.getHeadDepartmentClasses();
      setAllClasses(response.data);

      // Check if all teachers approved
      const allApproved = response.data.every(c => 
        c.workApproved && c.examApproved
      );
      setCanApproveDept(allApproved && response.data.length > 0);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل فصول القسم');
    } finally {
      setLoading(false);
    }
  }, []);

  // Load head's own classes (as teacher)
  const loadHeadClasses = useCallback(async () => {
    try {
      setLoading(true);
      const response = await gradeService.getTeacherClasses();
      setHeadClasses(response.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الفصول');
    } finally {
      setLoading(false);
    }
  }, []);

  // Review grades of a specific teacher
  const reviewTeacherGrades = useCallback(async (classId, teacherId, subjectId, period) => {
    try {
      setLoading(true);
      const response = await gradeService.getTeacherGradesForReview(classId, teacherId, subjectId, period);
      setReviewData(response.data);
      setSelectedClass(classId);
      setSelectedTeacher(teacherId);
      setSelectedSubject(subjectId);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الدرجات للمراجعة');
    } finally {
      setLoading(false);
    }
  }, []);

  // Head edits grades (before approval)
  const editGradeAsHead = useCallback(async (studentId, type, value) => {
    try {
      setLoading(true);
      await gradeService.editGradeAsHead({
        classId: selectedClass,
        teacherId: selectedTeacher,
        subjectId: selectedSubject,
        studentId,
        type,
        value
      });

      // Update local data
      setReviewData(prev => ({
        ...prev,
        grades: {
          ...prev.grades,
          [studentId]: {
            ...prev.grades[studentId],
            [type]: value,
            [`${type}EditedByHead`]: true
          }
        }
      }));
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تعديل الدرجة');
    } finally {
      setLoading(false);
    }
  }, [selectedClass, selectedTeacher, selectedSubject]);

  // Approve department grades (all classes)
  const approveDepartment = useCallback(async (type, period) => {
    try {
      setLoading(true);
      await gradeService.approveDepartment({
        type,
        period
      });

      setDeptApprovalStatus(prev => ({
        ...prev,
        [type]: 'approved'
      }));
      setError(null);
      return true;
    } catch (err) {
      setError(err.response?.data?.message || 'فشل اعتماد القسم');
      return false;
    } finally {
      setLoading(false);
    }
  }, []);

  // Check if teacher approved
  const isTeacherApproved = useCallback((classItem, type) => {
    return type === 'work' ? classItem.workApproved : classItem.examApproved;
  }, []);

  // Get approval status color
  const getApprovalColor = useCallback((classItem, type) => {
    const approved = isTeacherApproved(classItem, type);
    return approved ? 'bg-green-100 text-green-700 border-green-300' : 'bg-yellow-100 text-yellow-700 border-yellow-300';
  }, [isTeacherApproved]);

  return {
    loading,
    error,
    allClasses,
    headClasses,
    reviewData,
    selectedClass,
    selectedTeacher,
    selectedSubject,
    deptApprovalStatus,
    canApproveDept,
    setSelectedClass,
    setSelectedTeacher,
    setSelectedSubject,
    loadAllClasses,
    loadHeadClasses,
    reviewTeacherGrades,
    editGradeAsHead,
    approveDepartment,
    isTeacherApproved,
    getApprovalColor
  };
};
