using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Tenancy.Domain;

public static class TenancyErrors
{
    public static Error TenantNotFound(long tenantId) =>
        Error.NotFound("Tenancy.Tenant.NotFound", $"Tenant '{tenantId}' was not found.");

    public static Error TenantCodeAlreadyExists(string tenantCode) =>
        Error.Conflict("Tenancy.Tenant.CodeAlreadyExists", $"A tenant with code '{tenantCode}' already exists.");

    public static Error CompanyNotFound(long companyId) =>
        Error.NotFound("Tenancy.Company.NotFound", $"Company '{companyId}' was not found.");

    public static Error CompanyCodeAlreadyExists(string companyCode) =>
        Error.Conflict("Tenancy.Company.CodeAlreadyExists", $"A company with code '{companyCode}' already exists for this tenant.");

    public static Error CompanyLimitReached(int maxCompanies) =>
        Error.Conflict("Tenancy.Company.LimitReached", $"The tenant company limit of {maxCompanies} has been reached.");

    public static Error BranchNotFound(long branchId) =>
        Error.NotFound("Tenancy.Branch.NotFound", $"Branch '{branchId}' was not found.");

    public static Error BranchCodeAlreadyExists(string branchCode) =>
        Error.Conflict("Tenancy.Branch.CodeAlreadyExists", $"A branch with code '{branchCode}' already exists for this company.");

    public static Error SubscriptionStatusInvalid(string subscriptionStatus) =>
        Error.Validation("Tenancy.Tenant.SubscriptionStatusInvalid", $"Subscription status '{subscriptionStatus}' is not valid.");
}
