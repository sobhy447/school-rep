import { useState, useCallback } from 'react';
import { gradeService } from '../services/gradeService';

export const useExcuses = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [excuses, setExcuses] = useState([]);
  const [students, setStudents] = useState([]);
  const [classes, setClasses] = useState([]);
  const [subjects, setSubjects] = useState([]);

  // Load all excuses
  const loadExcuses = useCallback(async (filters = {}) => {
    try {
      setLoading(true);
      const response = await gradeService.getExcuses(filters);
      setExcuses(response.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الاعذار');
    } finally {
      setLoading(false);
    }
  }, []);

  // Load students for a class
  const loadStudents = useCallback(async (classId) => {
    try {
      setLoading(true);
      const response = await gradeService.getClassStudents(classId);
      setStudents(response.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الطلاب');
    } finally {
      setLoading(false);
    }
  }, []);

  // Load classes
  const loadClasses = useCallback(async () => {
    try {
      setLoading(true);
      const response = await gradeService.getAllClasses();
      setClasses(response.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الفصول');
    } finally {
      setLoading(false);
    }
  }, []);

  // Load subjects
  const loadSubjects = useCallback(async () => {
    try {
      setLoading(true);
      const response = await gradeService.getAllSubjects();
      setSubjects(response.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل المواد');
    } finally {
      setLoading(false);
    }
  }, []);

  // Add excuse
  const addExcuse = useCallback(async (excuseData) => {
    try {
      setLoading(true);
      await gradeService.addExcuse(excuseData);

      // Refresh list
      await loadExcuses();
      setError(null);
      return true;
    } catch (err) {
      setError(err.response?.data?.message || 'فشل إضافة العذر');
      return false;
    } finally {
      setLoading(false);
    }
  }, [loadExcuses]);

  // Delete excuse
  const deleteExcuse = useCallback(async (excuseId) => {
    try {
      setLoading(true);
      await gradeService.deleteExcuse(excuseId);

      // Refresh list
      await loadExcuses();
      setError(null);
      return true;
    } catch (err) {
      setError(err.response?.data?.message || 'فشل حذف العذر');
      return false;
    } finally {
      setLoading(false);
    }
  }, [loadExcuses]);

  // Check if student has excuse
  const hasExcuse = useCallback((studentId, subjectId, period, type) => {
    return excuses.some(e => 
      e.student.toString() === studentId &&
      e.subject.toString() === subjectId &&
      e.period === period &&
      e.type === type
    );
  }, [excuses]);

  return {
    loading,
    error,
    excuses,
    students,
    classes,
    subjects,
    loadExcuses,
    loadStudents,
    loadClasses,
    loadSubjects,
    addExcuse,
    deleteExcuse,
    hasExcuse
  };
};
