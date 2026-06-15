using Atlas.Modules.Tenancy.Domain.Branches;
using Atlas.Modules.Tenancy.Domain.Companies;
using Atlas.Modules.Tenancy.Domain.Tenants;
using Atlas.Modules.Tenancy.Infrastructure.Configurations;
using Atlas.MultiTenancy.Abstractions;
using Atlas.Persistence.PostgreSql.Context;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Modules.Tenancy.Infrastructure.Persistence;

public sealed class TenancyDbContext : AtlasDbContextBase
{
    public TenancyDbContext(DbContextOptions<TenancyDbContext> options, ITenantContext tenantContext)
        : base(options, tenantContext)
    {
    }

    protected override string SchemaName => "atlas";

    public DbSet<Tenant> Tenants => Set<Tenant>();

    public DbSet<Company> Companies => Set<Company>();

    public DbSet<Branch> Branches => Set<Branch>();

    protected override void ConfigureModel(ModelBuilder modelBuilder)
    {
        modelBuilder.ApplyConfiguration(new TenantConfiguration());
        modelBuilder.ApplyConfiguration(new CompanyConfiguration());
        modelBuilder.ApplyConfiguration(new BranchConfiguration());
    }
}
