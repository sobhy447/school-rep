import { useState, useCallback } from 'react';
import { gradeService } from '../services/gradeService';

export const useControl = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  // All approved grades data
  const [allGrades, setAllGrades] = useState([]);

  // Selected for review/print
  const [selectedGrade, setSelectedGrade] = useState(null);
  const [printData, setPrintData] = useState(null);

  // Filters
  const [filters, setFilters] = useState({
    gradeLevel: '',
    subject: '',
    period: 'first',
    type: 'all' // 'work', 'exam', 'all'
  });

  // Load all approved grades
  const loadAllGrades = useCallback(async (params = {}) => {
    try {
      setLoading(true);
      const response = await gradeService.getAllGrades({ ...filters, ...params });
      setAllGrades(response.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الدرجات');
    } finally {
      setLoading(false);
    }
  }, [filters]);

  // Get print data for a specific class/subject
  const loadPrintData = useCallback(async (classId, subjectId, period, type) => {
    try {
      setLoading(true);
      const response = await gradeService.getPrintData({ classId, subjectId, period, type });
      setPrintData(response.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل بيانات الطباعة');
    } finally {
      setLoading(false);
    }
  }, []);

  // Filter handlers
  const updateFilter = useCallback((key, value) => {
    setFilters(prev => ({ ...prev, [key]: value }));
  }, []);

  // Get unique grade levels
  const getGradeLevels = useCallback(() => {
    const levels = [...new Set(allGrades.map(g => g.gradeLevel))];
    return levels.sort((a, b) => a - b);
  }, [allGrades]);

  // Get unique subjects
  const getSubjects = useCallback(() => {
    const subjects = [...new Set(allGrades.map(g => g.subjectName))];
    return subjects.sort();
  }, [allGrades]);

  // Filter grades
  const getFilteredGrades = useCallback(() => {
    return allGrades.filter(grade => {
      if (filters.gradeLevel && grade.gradeLevel !== parseInt(filters.gradeLevel)) return false;
      if (filters.subject && grade.subjectName !== filters.subject) return false;
      if (filters.period && grade.period !== filters.period) return false;
      return true;
    });
  }, [allGrades, filters]);

  // Check if all departments approved
  const isFullyApproved = useCallback((grade) => {
    return grade.workDeptApproved && grade.examDeptApproved;
  }, []);

  return {
    loading,
    error,
    allGrades,
    selectedGrade,
    printData,
    filters,
    setSelectedGrade,
    loadAllGrades,
    loadPrintData,
    updateFilter,
    getGradeLevels,
    getSubjects,
    getFilteredGrades,
    isFullyApproved
  };
};
