using Atlas.Modules.Tenancy.Domain.Tenants;
using Atlas.Persistence.PostgreSql.Configurations;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Atlas.Modules.Tenancy.Infrastructure.Configurations;

internal sealed class TenantConfiguration : EntityTypeConfiguration<Tenant>
{
    protected override void ConfigureEntity(EntityTypeBuilder<Tenant> builder)
    {
        builder.ToTable("tenants");

        builder.HasKey(tenant => tenant.Id);
        builder.Property(tenant => tenant.Id).HasColumnName("tenant_id").ValueGeneratedOnAdd();

        builder.Property(tenant => tenant.TenantCode)
            .HasColumnName("tenant_code")
            .HasMaxLength(Tenant.CodeMaxLength)
            .IsRequired();
        builder.HasIndex(tenant => tenant.TenantCode).IsUnique().HasDatabaseName("uq_tenants_code");

        builder.OwnsOne(tenant => tenant.Name, name =>
        {
            name.Property(value => value.Arabic)
                .HasColumnName("tenant_name_ar").HasMaxLength(500).IsRequired();
            name.Property(value => value.English)
                .HasColumnName("tenant_name_en").HasMaxLength(500).IsRequired();
        });
        builder.Navigation(tenant => tenant.Name).IsRequired();

        builder.Property(tenant => tenant.SubscriptionStatus)
            .HasColumnName("subscription_status")
            .HasMaxLength(50)
            .IsRequired()
            .HasConversion(status => status.Name, name => SubscriptionStatus.FromName(name)!);

        builder.Property(tenant => tenant.MaxCompanies).HasColumnName("max_companies").IsRequired();
        builder.Property(tenant => tenant.MaxUsers).HasColumnName("max_users").IsRequired();
        builder.Property(tenant => tenant.BaseCurrencyId).HasColumnName("base_currency_id").IsRequired();
        builder.Property(tenant => tenant.DefaultLanguage).HasColumnName("default_language").HasMaxLength(10).IsRequired();
        builder.Property(tenant => tenant.TimeZone).HasColumnName("time_zone").HasMaxLength(Tenant.TimeZoneMaxLength).IsRequired();
        builder.Property(tenant => tenant.IsActive).HasColumnName("is_active").IsRequired();

        builder.Property(tenant => tenant.IsDeleted).HasColumnName("is_deleted").IsRequired();
        builder.Property(tenant => tenant.DeletedAtUtc).HasColumnName("deleted_at");
        builder.Property(tenant => tenant.DeletedBy).HasColumnName("deleted_by");
        builder.Property(tenant => tenant.CreatedAtUtc).HasColumnName("created_at").IsRequired();
        builder.Property(tenant => tenant.CreatedBy).HasColumnName("created_by").IsRequired();
        builder.Property(tenant => tenant.UpdatedAtUtc).HasColumnName("updated_at");
        builder.Property(tenant => tenant.UpdatedBy).HasColumnName("updated_by");
    }
}
