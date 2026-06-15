namespace Atlas.Modules.Tenancy.Contracts.Tenants;

public sealed record UpdateTenantRequest(
    string NameAr,
    string NameEn,
    int MaxCompanies,
    int MaxUsers,
    string DefaultLanguage,
    string TimeZone,
    string SubscriptionStatus);
