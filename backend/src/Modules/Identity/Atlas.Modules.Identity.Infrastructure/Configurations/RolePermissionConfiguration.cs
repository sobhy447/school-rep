using Atlas.Modules.Identity.Domain.RolePermissions;
using Atlas.Persistence.PostgreSql.Configurations;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Atlas.Modules.Identity.Infrastructure.Configurations;

internal sealed class RolePermissionConfiguration : EntityTypeConfiguration<RolePermission>
{
    protected override void ConfigureEntity(EntityTypeBuilder<RolePermission> builder)
    {
        builder.ToTable("role_permissions");

        builder.HasKey(rolePermission => rolePermission.Id);
        builder.Property(rolePermission => rolePermission.Id).HasColumnName("role_permission_id").ValueGeneratedOnAdd();

        builder.Property(rolePermission => rolePermission.TenantId).HasColumnName("tenant_id").IsRequired();
        builder.Property(rolePermission => rolePermission.RoleId).HasColumnName("role_id").IsRequired();
        builder.Property(rolePermission => rolePermission.PermissionId).HasColumnName("permission_id").IsRequired();
        builder.Property(rolePermission => rolePermission.CreatedAtUtc)
            .HasColumnName("created_at")
            .HasDefaultValueSql("NOW()")
            .ValueGeneratedOnAdd()
            .IsRequired();
        builder.Property(rolePermission => rolePermission.CreatedBy).HasColumnName("created_by").IsRequired();

        builder.HasIndex(rolePermission => new { rolePermission.TenantId, rolePermission.RoleId, rolePermission.PermissionId })
            .IsUnique()
            .HasDatabaseName("uq_role_permissions");
    }
}
