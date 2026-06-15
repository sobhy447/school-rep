using Atlas.Modules.Tenancy.Contracts.Branches;
using Atlas.Modules.Tenancy.Contracts.Companies;
using Atlas.Modules.Tenancy.Contracts.Tenants;
using Atlas.Modules.Tenancy.Domain.Branches;
using Atlas.Modules.Tenancy.Domain.Companies;
using Atlas.Modules.Tenancy.Domain.Tenants;

namespace Atlas.Modules.Tenancy.Application.Common;

internal static class TenancyMapper
{
    public static TenantResponse ToResponse(Tenant tenant) =>
        new(
            tenant.Id,
            tenant.TenantCode,
            tenant.Name.Arabic,
            tenant.Name.English,
            tenant.SubscriptionStatus.Name,
            tenant.MaxCompanies,
            tenant.MaxUsers,
            tenant.BaseCurrencyId,
            tenant.DefaultLanguage,
            tenant.TimeZone,
            tenant.IsActive);

    public static CompanyResponse ToResponse(Company company) =>
        new(
            company.Id,
            company.TenantId,
            company.CompanyCode,
            company.Name.Arabic,
            company.Name.English,
            company.TaxNumber,
            company.RegistrationNo,
            company.BaseCurrencyId,
            company.Address.Line1,
            company.Address.Line2,
            company.Address.City,
            company.Address.CountryCode,
            company.Phone,
            company.Email,
            company.LogoUrl,
            company.FiscalYearStartMonth,
            company.IsActive,
            company.EntityVersion);

    public static BranchResponse ToResponse(Branch branch) =>
        new(
            branch.Id,
            branch.TenantId,
            branch.CompanyId,
            branch.BranchCode,
            branch.Name.Arabic,
            branch.Name.English,
            branch.Address,
            branch.Phone,
            branch.IsMainBranch,
            branch.IsActive,
            branch.EntityVersion);
}
