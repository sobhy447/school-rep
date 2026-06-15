using Atlas.Modules.Identity.Domain.UserRoles;
using Atlas.Persistence.PostgreSql.Configurations;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Atlas.Modules.Identity.Infrastructure.Configurations;

internal sealed class UserRoleConfiguration : EntityTypeConfiguration<UserRole>
{
    protected override void ConfigureEntity(EntityTypeBuilder<UserRole> builder)
    {
        builder.ToTable("user_roles");

        builder.HasKey(userRole => userRole.Id);
        builder.Property(userRole => userRole.Id).HasColumnName("user_role_id").ValueGeneratedOnAdd();

        builder.Property(userRole => userRole.TenantId).HasColumnName("tenant_id").IsRequired();
        builder.Property(userRole => userRole.UserId).HasColumnName("user_id").IsRequired();
        builder.Property(userRole => userRole.RoleId).HasColumnName("role_id").IsRequired();
        builder.Property(userRole => userRole.CompanyId).HasColumnName("company_id");
        builder.Property(userRole => userRole.AssignedAtUtc)
            .HasColumnName("assigned_at")
            .HasDefaultValueSql("NOW()")
            .ValueGeneratedOnAdd()
            .IsRequired();
        builder.Property(userRole => userRole.AssignedBy).HasColumnName("assigned_by").IsRequired();
        builder.Property(userRole => userRole.ExpiresAtUtc).HasColumnName("expires_at");

        builder.HasIndex(userRole => new { userRole.TenantId, userRole.UserId, userRole.RoleId, userRole.CompanyId })
            .IsUnique()
            .HasDatabaseName("uq_user_roles");
    }
}
