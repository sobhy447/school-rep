namespace Atlas.Modules.Tenancy.Contracts.Companies;

public sealed record CompanyResponse(
    long CompanyId,
    long TenantId,
    string CompanyCode,
    string NameAr,
    string NameEn,
    string? TaxNumber,
    string? RegistrationNo,
    long BaseCurrencyId,
    string? AddressLine1,
    string? AddressLine2,
    string? City,
    string CountryCode,
    string? Phone,
    string? Email,
    string? LogoUrl,
    short FiscalYearStartMonth,
    bool IsActive,
    int EntityVersion);
