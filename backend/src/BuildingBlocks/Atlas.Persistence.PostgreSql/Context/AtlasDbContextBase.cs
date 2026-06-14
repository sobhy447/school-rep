using Atlas.Messaging.Outbox.Model;
using Atlas.MultiTenancy.Abstractions;
using Atlas.Persistence.PostgreSql.Configurations;
using Atlas.Persistence.PostgreSql.Conventions;
using Atlas.Persistence.PostgreSql.Query;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Persistence.PostgreSql.Context;

public abstract class AtlasDbContextBase : DbContext
{
    private readonly ITenantContext _tenantContext;

    protected AtlasDbContextBase(DbContextOptions options, ITenantContext tenantContext)
        : base(options) =>
        _tenantContext = tenantContext;

    public long CurrentTenantId => _tenantContext.TenantId;

    protected abstract string SchemaName { get; }

    internal DbSet<OutboxMessage> OutboxMessages => Set<OutboxMessage>();

    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        base.OnModelCreating(modelBuilder);

        modelBuilder.HasDefaultSchema(SchemaName);
        modelBuilder.ApplyConfiguration(new OutboxMessageConfiguration());

        ConfigureModel(modelBuilder);

        GlobalQueryFilters.ApplySoftDeleteFilter(modelBuilder);
        modelBuilder.ApplySnakeCaseNames();
    }

    protected override void ConfigureConventions(ModelConfigurationBuilder configurationBuilder)
    {
        base.ConfigureConventions(configurationBuilder);
        configurationBuilder.ApplyAtlasTypeConventions();
    }

    protected abstract void ConfigureModel(ModelBuilder modelBuilder);
}
