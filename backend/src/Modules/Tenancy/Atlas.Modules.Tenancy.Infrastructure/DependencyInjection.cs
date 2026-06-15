using Atlas.Modules.Tenancy.Domain.Branches;
using Atlas.Modules.Tenancy.Domain.Companies;
using Atlas.Modules.Tenancy.Domain.Tenants;
using Atlas.Modules.Tenancy.Infrastructure.Persistence;
using Atlas.Persistence.PostgreSql;
using Microsoft.Extensions.DependencyInjection;

namespace Atlas.Modules.Tenancy.Infrastructure;

public static class DependencyInjection
{
    public static IServiceCollection AddTenancyInfrastructure(this IServiceCollection services)
    {
        services.AddAtlasDbContext<TenancyDbContext>();

        services.AddScoped<ITenantRepository, TenantRepository>();
        services.AddScoped<ICompanyRepository, CompanyRepository>();
        services.AddScoped<IBranchRepository, BranchRepository>();

        return services;
    }
}
