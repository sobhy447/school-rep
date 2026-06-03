import { useState, useCallback, useEffect } from 'react';
import { gradeService } from '../services/gradeService';
import { useAuth } from '../context/AuthContext';

export const useGrades = () => {
  const { user } = useAuth();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [classes, setClasses] = useState([]);
  const [selectedClass, setSelectedClass] = useState(null);
  const [selectedSubject, setSelectedSubject] = useState(null);
  const [students, setStudents] = useState([]);
  const [grades, setGrades] = useState({});
  const [excuses, setExcuses] = useState({});
  const [approvalStatus, setApprovalStatus] = useState({});
  const [period, setPeriod] = useState('first');
  const [settings, setSettings] = useState({
    workEnabled: true,
    examEnabled: true,
    workMax: 40,
    examMax: 60,
    allowHalf: true
  });

  // Load teacher's classes
  const loadClasses = useCallback(async () => {
    try {
      setLoading(true);
      const response = await gradeService.getTeacherClasses();
      setClasses(response.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الفصول');
    } finally {
      setLoading(false);
    }
  }, []);

  // Load students and grades for selected class
  const loadGrades = useCallback(async (classId, subjectId, period) => {
    if (!classId || !subjectId) return;
    try {
      setLoading(true);
      const response = await gradeService.getClassGrades(classId, subjectId, period);
      setStudents(response.data.students);
      setGrades(response.data.grades || {});
      setExcuses(response.data.excuses || {});
      setApprovalStatus(response.data.approvalStatus || {});
      setSettings(response.data.settings || settings);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الدرجات');
    } finally {
      setLoading(false);
    }
  }, []);

  // Update grade for a student
  const updateGrade = useCallback((studentId, type, value) => {
    // Validate
    const max = type === 'work' ? settings.workMax : settings.examMax;
    let numValue = parseFloat(value);

    if (isNaN(numValue) || numValue < 0) numValue = 0;
    if (numValue > max) numValue = max;

    // Check half points
    if (settings.allowHalf) {
      const decimal = numValue % 1;
      if (decimal !== 0 && decimal !== 0.5) {
        numValue = Math.floor(numValue);
      }
    } else {
      numValue = Math.floor(numValue);
    }

    setGrades(prev => ({
      ...prev,
      [studentId]: {
        ...prev[studentId],
        [type]: numValue,
        [`${type}Saved`]: false
      }
    }));
  }, [settings]);

  // Save grades (draft)
  const saveGrades = useCallback(async () => {
    try {
      setLoading(true);
      await gradeService.saveGrades({
        classId: selectedClass,
        subjectId: selectedSubject,
        period,
        grades
      });

      // Mark as saved
      setGrades(prev => {
        const updated = {};
        Object.keys(prev).forEach(id => {
          updated[id] = {
            ...prev[id],
            workSaved: true,
            examSaved: true
          };
        });
        return updated;
      });

      setError(null);
      return true;
    } catch (err) {
      setError(err.response?.data?.message || 'فشل حفظ الدرجات');
      return false;
    } finally {
      setLoading(false);
    }
  }, [selectedClass, selectedSubject, period, grades]);

  // Submit grades for approval
  const submitGrades = useCallback(async (type) => {
    try {
      setLoading(true);

      // Check all students have grades
      const missing = students.filter(s => 
        !grades[s._id]?.[type] && !excuses[s._id]?.[type]
      );

      if (missing.length > 0) {
        setError(`يوجد ${missing.length} طالب بدون درجة - لا يمكن الاعتماد`);
        return false;
      }

      await gradeService.submitGrades({
        classId: selectedClass,
        subjectId: selectedSubject,
        period,
        type,
        grades
      });

      setApprovalStatus(prev => ({
        ...prev,
        [type]: 'submitted'
      }));

      setError(null);
      return true;
    } catch (err) {
      setError(err.response?.data?.message || 'فشل اعتماد الدرجات');
      return false;
    } finally {
      setLoading(false);
    }
  }, [selectedClass, selectedSubject, period, grades, students, excuses]);

  // Check if student has excuse
  const hasExcuse = useCallback((studentId, type) => {
    return excuses[studentId]?.[type] || false;
  }, [excuses]);

  // Check if can edit
  const canEdit = useCallback((type) => {
    return approvalStatus[type] !== 'submitted' && 
           approvalStatus[type] !== 'approved' &&
           approvalStatus[type] !== 'head_approved';
  }, [approvalStatus]);

  return {
    loading,
    error,
    classes,
    students,
    grades,
    excuses,
    approvalStatus,
    period,
    settings,
    selectedClass,
    selectedSubject,
    setSelectedClass,
    setSelectedSubject,
    setPeriod,
    loadClasses,
    loadGrades,
    updateGrade,
    saveGrades,
    submitGrades,
    hasExcuse,
    canEdit
  };
};
