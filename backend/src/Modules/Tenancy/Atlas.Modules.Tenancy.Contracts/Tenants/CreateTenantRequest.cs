namespace Atlas.Modules.Tenancy.Contracts.Tenants;

public sealed record CreateTenantRequest(
    string TenantCode,
    string NameAr,
    string NameEn,
    long BaseCurrencyId,
    int MaxCompanies,
    int MaxUsers,
    string DefaultLanguage,
    string TimeZone);
