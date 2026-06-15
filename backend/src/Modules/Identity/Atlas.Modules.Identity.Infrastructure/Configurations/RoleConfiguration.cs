using Atlas.Modules.Identity.Domain.Roles;
using Atlas.Persistence.PostgreSql.Configurations;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Atlas.Modules.Identity.Infrastructure.Configurations;

internal sealed class RoleConfiguration : EntityTypeConfiguration<Role>
{
    protected override void ConfigureEntity(EntityTypeBuilder<Role> builder)
    {
        builder.ToTable("roles");

        builder.HasKey(role => role.Id);
        builder.Property(role => role.Id).HasColumnName("role_id").ValueGeneratedOnAdd();

        builder.Property(role => role.TenantId).HasColumnName("tenant_id");

        builder.Property(role => role.RoleCode)
            .HasColumnName("role_code")
            .HasMaxLength(Role.CodeMaxLength)
            .IsRequired();
        builder.HasIndex(role => new { role.TenantId, role.RoleCode })
            .IsUnique()
            .HasDatabaseName("uq_roles_code");

        builder.OwnsOne(role => role.Name, name =>
        {
            name.Property(value => value.Arabic).HasColumnName("role_name_ar").HasMaxLength(300).IsRequired();
            name.Property(value => value.English).HasColumnName("role_name_en").HasMaxLength(300).IsRequired();
        });
        builder.Navigation(role => role.Name).IsRequired();

        builder.Property(role => role.IsSystemRole).HasColumnName("is_system_role").IsRequired();
        builder.Property(role => role.IsActive).HasColumnName("is_active").IsRequired();

        builder.Property(role => role.IsDeleted).HasColumnName("is_deleted").IsRequired();
        builder.Property(role => role.DeletedAtUtc).HasColumnName("deleted_at");
        builder.Property(role => role.DeletedBy).HasColumnName("deleted_by");
        builder.Property(role => role.CreatedAtUtc).HasColumnName("created_at").IsRequired();
        builder.Property(role => role.CreatedBy).HasColumnName("created_by").IsRequired();
        builder.Property(role => role.UpdatedAtUtc).HasColumnName("updated_at");
        builder.Property(role => role.UpdatedBy).HasColumnName("updated_by");
    }
}
