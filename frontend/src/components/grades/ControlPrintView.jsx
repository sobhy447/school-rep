import React, { useRef } from 'react';
import { ArrowRight, Printer, Download } from 'lucide-react';

const ControlPrintView = ({ printData, onBack }) => {
  const printRef = useRef();

  if (!printData) return null;

  const { 
    schoolInfo, 
    classInfo, 
    subjectInfo, 
    teacherInfo, 
    headInfo, 
    period,
    students, 
    grades, 
    excuses,
    ministryLogo,
    schoolLogo 
  } = printData;

  const handlePrint = () => {
    const printContents = printRef.current.innerHTML;
    const originalContents = document.body.innerHTML;

    document.body.innerHTML = `
      <html dir="rtl">
        <head>
          <title>كشف رصد الدرجات - ${subjectInfo.name} - ${classInfo.name}</title>
          <style>
            body { font-family: 'Noto Sans Arabic', Arial, sans-serif; margin: 0; padding: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #333; padding: 8px; text-align: center; }
            th { background: #f0f0f0; font-weight: bold; }
            .header { text-align: center; margin-bottom: 20px; }
            .footer { margin-top: 30px; display: flex; justify-content: space-between; }
            .signature { text-align: center; margin-top: 50px; }
            .signature-line { border-top: 1px solid #333; width: 200px; margin: 10px auto; }
            @media print { body { padding: 0; } }
          </style>
        </head>
        <body>${printContents}</body>
      </html>
    `;

    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
  };

  const getGradeColor = (grade, max) => {
    if (!grade && grade !== 0) return '';
    const pct = (grade / max) * 100;
    if (pct >= 90) return 'color: green; font-weight: bold;';
    if (pct >= 75) return 'color: blue;';
    if (pct >= 60) return 'color: orange;';
    return 'color: red; font-weight: bold;';
  };

  return (
    <div className="space-y-6">
      {/* Actions */}
      <div className="flex items-center justify-between">
        <button
          onClick={onBack}
          className="flex items-center gap-2 text-gray-600 hover:text-gray-800 transition-colors"
        >
          <ArrowRight className="w-5 h-5" />
          رجوع
        </button>
        <div className="flex gap-3">
          <button
            onClick={handlePrint}
            className="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
          >
            <Printer className="w-4 h-4" />
            طباعة
          </button>
          <button
            onClick={() => alert('سيتم إضافة تحميل PDF قريباً')}
            className="flex items-center gap-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
          >
            <Download className="w-4 h-4" />
            تحميل PDF
          </button>
        </div>
      </div>

      {/* Print Content */}
      <div ref={printRef} className="bg-white p-8 border-2 border-gray-800 shadow-lg">
        {/* Header */}
        <div className="header flex items-center justify-between mb-6 pb-4 border-b-2 border-gray-800">
          <div className="w-24">
            {ministryLogo && (
              <img src={ministryLogo} alt="شعار الوزارة" className="w-20 h-20 object-contain" />
            )}
            {!ministryLogo && (
              <div className="w-20 h-20 bg-gray-200 rounded flex items-center justify-center text-xs text-gray-500">
                شعار الوزارة
              </div>
            )}
          </div>

          <div className="text-center flex-1">
            <h1 className="text-xl font-bold text-gray-900 mb-1">
              {schoolInfo?.country || 'دولة الكويت'}
            </h1>
            <h2 className="text-lg font-bold text-gray-800 mb-1">
              {schoolInfo?.ministry || 'وزارة التربية'}
            </h2>
            <h3 className="text-md font-bold text-gray-700">
              {schoolInfo?.name || 'اسم المدرسة'}
            </h3>
            <div className="mt-2 text-sm text-gray-600">
              {schoolInfo?.address || ''}
            </div>
          </div>

          <div className="w-24">
            {schoolLogo && (
              <img src={schoolLogo} alt="شعار المدرسة" className="w-20 h-20 object-contain" />
            )}
            {!schoolLogo && (
              <div className="w-20 h-20 bg-gray-200 rounded flex items-center justify-center text-xs text-gray-500">
                شعار المدرسة
              </div>
            )}
          </div>
        </div>

        {/* Title */}
        <div className="text-center mb-6">
          <h2 className="text-2xl font-bold text-gray-900 border-2 border-gray-800 inline-block px-8 py-2">
            كشف رصد الدرجات
          </h2>
        </div>

        {/* Info */}
        <div className="grid grid-cols-2 gap-4 mb-6 text-sm">
          <div className="border border-gray-400 p-3">
            <span className="font-bold">المادة:</span> {subjectInfo?.name}
          </div>
          <div className="border border-gray-400 p-3">
            <span className="font-bold">الفصل:</span> {classInfo?.name}
          </div>
          <div className="border border-gray-400 p-3">
            <span className="font-bold">الفترة:</span> {period === 'first' ? 'الأولى' : 'الثانية'}
          </div>
          <div className="border border-gray-400 p-3">
            <span className="font-bold">السنة الدراسية:</span> {schoolInfo?.academicYear || '2025-2026'}
          </div>
        </div>

        {/* Grades Table */}
        <table className="w-full">
          <thead>
            <tr>
              <th className="w-12">م</th>
              <th>اسم الطالب</th>
              <th className="w-24">الأعمال<br/>(40)</th>
              <th className="w-24">الاختبار<br/>(60)</th>
              <th className="w-24">المجموع<br/>(100)</th>
              <th className="w-32">ملاحظات</th>
            </tr>
          </thead>
          <tbody>
            {students?.map((student, index) => {
              const studentGrades = grades?.[student._id] || {};
              const work = studentGrades.work;
              const exam = studentGrades.exam;
              const total = (work || 0) + (exam || 0);
              const hasExcuse = excuses?.[student._id];

              return (
                <tr key={student._id}>
                  <td>{index + 1}</td>
                  <td className="text-right">
                    <div className="font-medium">{student.name}</div>
                    <div className="text-xs text-gray-500">{student.studentId}</div>
                  </td>
                  <td>
                    {hasExcuse?.work ? (
                      <span className="text-red-600 font-bold">معذور</span>
                    ) : (
                      <span style={getGradeColor(work, 40)}>{work !== undefined ? work : '-'}</span>
                    )}
                  </td>
                  <td>
                    {hasExcuse?.exam ? (
                      <span className="text-red-600 font-bold">معذور</span>
                    ) : (
                      <span style={getGradeColor(exam, 60)}>{exam !== undefined ? exam : '-'}</span>
                    )}
                  </td>
                  <td>
                    <span style={getGradeColor(total, 100)} className="font-bold">
                      {(work !== undefined && exam !== undefined) ? total : '-'}
                    </span>
                  </td>
                  <td className="text-xs">
                    {studentGrades.workEditedByHead && (
                      <div className="text-blue-600">تعديل رئيس قسم</div>
                    )}
                    {hasExcuse && (
                      <div className="text-red-600">عذر</div>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>

        {/* Footer Signatures */}
        <div className="footer mt-8">
          <div className="signature">
            <div className="font-bold mb-1">المعلم</div>
            <div className="text-sm">{teacherInfo?.name}</div>
            <div className="text-xs text-gray-600 mt-1">
              تاريخ الاعتماد: {teacherInfo?.approvalDate || '___/___/______'}
            </div>
            <div className="signature-line"></div>
            <div className="text-xs text-gray-500 mt-1">التوقيع</div>
          </div>

          <div className="signature">
            <div className="font-bold mb-1">المراجع</div>
            <div className="text-xs text-gray-600 mt-1">تاريخ المراجعة: ___/___/______</div>
            <div className="signature-line"></div>
            <div className="text-xs text-gray-500 mt-1">التوقيع</div>
          </div>

          <div className="signature">
            <div className="font-bold mb-1">رئيس القسم</div>
            <div className="text-sm">{headInfo?.name}</div>
            <div className="text-xs text-gray-600 mt-1">
              تاريخ الاعتماد: {headInfo?.approvalDate || '___/___/______'}
            </div>
            <div className="signature-line"></div>
            <div className="text-xs text-gray-500 mt-1">التوقيع</div>
          </div>
        </div>

        {/* Director */}
        <div className="mt-8 text-center">
          <div className="font-bold mb-1">المدير</div>
          <div className="text-sm">{schoolInfo?.director || '_________________'}</div>
          <div className="signature-line mx-auto"></div>
          <div className="text-xs text-gray-500 mt-1">التوقيع</div>
        </div>

        {/* Page Footer */}
        <div className="mt-8 pt-4 border-t border-gray-400 text-center text-xs text-gray-500">
          <p>هذا الكشف رسمي ومعتمد من وزارة التربية - {schoolInfo?.name}</p>
          <p>تم إنشاؤه بتاريخ: {new Date().toLocaleDateString('ar-KW')}</p>
        </div>
      </div>
    </div>
  );
};

export default ControlPrintView;
