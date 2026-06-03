import React from 'react';
import { ArrowRight, Printer, CheckCircle, AlertCircle, Lock, User, BookOpen, Calendar } from 'lucide-react';

const ControlReview = ({ grade, onBack, onPrint }) => {
  if (!grade) return null;

  return (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm">
      {/* Header */}
      <div className="p-4 border-b border-gray-200">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <button
              onClick={onBack}
              className="flex items-center gap-2 text-gray-600 hover:text-gray-800 transition-colors"
            >
              <ArrowRight className="w-5 h-5" />
              رجوع
            </button>
            <div className="h-6 w-px bg-gray-300"></div>
            <div>
              <h2 className="font-bold text-gray-800">مراجعة الكشف</h2>
              <p className="text-sm text-gray-500">
                {grade.className} | {grade.subjectName} | {grade.teacherName}
              </p>
            </div>
          </div>
          <button
            onClick={onPrint}
            className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
          >
            <Printer className="w-4 h-4" />
            طباعة الكشف
          </button>
        </div>
      </div>

      {/* Info Cards */}
      <div className="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-gray-50 rounded-lg p-3 border border-gray-200">
          <div className="flex items-center gap-2 text-gray-600 mb-1">
            <User className="w-4 h-4" />
            <span className="text-sm">المعلم</span>
          </div>
          <div className="font-bold text-gray-800">{grade.teacherName}</div>
          <div className="text-xs text-gray-500 mt-1">
            اعتماد: {grade.teacherApprovalDate || 'غير متوفر'}
          </div>
        </div>

        <div className="bg-gray-50 rounded-lg p-3 border border-gray-200">
          <div className="flex items-center gap-2 text-gray-600 mb-1">
            <BookOpen className="w-4 h-4" />
            <span className="text-sm">رئيس القسم</span>
          </div>
          <div className="font-bold text-gray-800">{grade.headName || 'غير متوفر'}</div>
          <div className="text-xs text-gray-500 mt-1">
            اعتماد: {grade.headApprovalDate || 'غير متوفر'}
          </div>
        </div>

        <div className="bg-gray-50 rounded-lg p-3 border border-gray-200">
          <div className="flex items-center gap-2 text-gray-600 mb-1">
            <Calendar className="w-4 h-4" />
            <span className="text-sm">الفترة</span>
          </div>
          <div className="font-bold text-gray-800">
            {grade.period === 'first' ? 'الفترة الأولى' : 'الفترة الثانية'}
          </div>
          <div className="text-xs text-gray-500 mt-1">
            {grade.students?.length || 0} طالب
          </div>
        </div>
      </div>

      {/* Grades Preview */}
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-gray-50 text-gray-700">
              <th className="px-4 py-3 text-right font-semibold w-12">#</th>
              <th className="px-4 py-3 text-right font-semibold">اسم الطالب</th>
              <th className="px-4 py-3 text-center font-semibold">الأعمال (40)</th>
              <th className="px-4 py-3 text-center font-semibold">الاختبار (60)</th>
              <th className="px-4 py-3 text-center font-semibold">الإجمالي</th>
              <th className="px-4 py-3 text-center font-semibold">الملاحظات</th>
            </tr>
          </thead>
          <tbody>
            {grade.students?.map((student, index) => {
              const studentGrades = grade.grades?.[student._id] || {};
              const work = studentGrades.work;
              const exam = studentGrades.exam;
              const total = (work || 0) + (exam || 0);
              const hasExcuse = grade.excuses?.[student._id];

              return (
                <tr key={student._id} className={`border-b ${index % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'}`}>
                  <td className="px-4 py-2 text-center text-gray-500">{index + 1}</td>
                  <td className="px-4 py-2">
                    <div className="font-medium text-gray-800">{student.name}</div>
                    <div className="text-xs text-gray-500">{student.studentId}</div>
                  </td>
                  <td className="px-4 py-2 text-center">
                    {hasExcuse?.work ? (
                      <span className="inline-flex items-center gap-1 text-red-500 text-xs">
                        <Lock className="w-3 h-3" />
                        معذور
                      </span>
                    ) : (
                      <span className={`font-bold ${
                        work >= 32 ? 'text-green-600' : work >= 24 ? 'text-blue-600' : work >= 16 ? 'text-yellow-600' : 'text-red-600'
                      }`}>
                        {work !== undefined ? work : '-'}
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-2 text-center">
                    {hasExcuse?.exam ? (
                      <span className="inline-flex items-center gap-1 text-red-500 text-xs">
                        <Lock className="w-3 h-3" />
                        معذور
                      </span>
                    ) : (
                      <span className={`font-bold ${
                        exam >= 48 ? 'text-green-600' : exam >= 36 ? 'text-blue-600' : exam >= 24 ? 'text-yellow-600' : 'text-red-600'
                      }`}>
                        {exam !== undefined ? exam : '-'}
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-2 text-center">
                    <span className={`font-bold text-lg ${
                      total >= 90 ? 'text-green-600' : total >= 75 ? 'text-blue-600' : total >= 60 ? 'text-yellow-600' : 'text-red-600'
                    }`}>
                      {(work !== undefined && exam !== undefined) ? total : '-'}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-center text-xs text-gray-500">
                    {studentGrades.workEditedByHead && (
                      <span className="inline-block px-2 py-0.5 bg-blue-100 text-blue-700 rounded mr-1">
                        تعديل رئيس قسم
                      </span>
                    )}
                    {hasExcuse && (
                      <span className="inline-block px-2 py-0.5 bg-red-100 text-red-700 rounded">
                        عذر
                      </span>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Footer */}
      <div className="p-4 border-t border-gray-200 bg-gray-50">
        <div className="flex items-center justify-between text-sm text-gray-600">
          <div>
            <span className="font-medium">المجموع:</span>
            <span className="mr-1">{grade.students?.length || 0} طالب</span>
          </div>
          <div className="flex items-center gap-4">
            <span className="inline-flex items-center gap-1">
              <CheckCircle className="w-4 h-4 text-green-500" />
              معتمد من المعلم
            </span>
            <span className="inline-flex items-center gap-1">
              <CheckCircle className="w-4 h-4 text-green-500" />
              معتمد من رئيس القسم
            </span>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ControlReview;
