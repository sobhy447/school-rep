namespace Atlas.Modules.Tenancy.Contracts.Tenants;

public sealed record TenantResponse(
    long TenantId,
    string TenantCode,
    string NameAr,
    string NameEn,
    string SubscriptionStatus,
    int MaxCompanies,
    int MaxUsers,
    long BaseCurrencyId,
    string DefaultLanguage,
    string TimeZone,
    bool IsActive);
