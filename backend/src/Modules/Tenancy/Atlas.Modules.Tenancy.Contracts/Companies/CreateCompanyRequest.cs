namespace Atlas.Modules.Tenancy.Contracts.Companies;

public sealed record CreateCompanyRequest(
    string CompanyCode,
    string NameAr,
    string NameEn,
    long BaseCurrencyId,
    string? TaxNumber,
    string? RegistrationNo,
    string? AddressLine1,
    string? AddressLine2,
    string? City,
    string CountryCode,
    string? Phone,
    string? Email,
    string? LogoUrl,
    short FiscalYearStartMonth);
