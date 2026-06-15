using Atlas.Modules.Identity.Domain.Permissions;
using Atlas.Persistence.PostgreSql.Configurations;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Atlas.Modules.Identity.Infrastructure.Configurations;

internal sealed class PermissionConfiguration : EntityTypeConfiguration<Permission>
{
    protected override void ConfigureEntity(EntityTypeBuilder<Permission> builder)
    {
        builder.ToTable("permissions");

        builder.HasKey(permission => permission.Id);
        builder.Property(permission => permission.Id).HasColumnName("permission_id").ValueGeneratedOnAdd();

        builder.Property(permission => permission.PermissionCode)
            .HasColumnName("permission_code")
            .HasMaxLength(Permission.CodeMaxLength)
            .IsRequired();
        builder.HasIndex(permission => permission.PermissionCode)
            .IsUnique()
            .HasDatabaseName("uq_permissions_code");

        builder.Property(permission => permission.Module).HasColumnName("module").HasMaxLength(Permission.ModuleMaxLength).IsRequired();

        builder.Property(permission => permission.Action)
            .HasConversion(action => action.Name, name => PermissionAction.FromName(name)!)
            .HasColumnName("action")
            .HasMaxLength(100)
            .IsRequired();

        builder.Property(permission => permission.Scope)
            .HasConversion(scope => scope.Name, name => PermissionScope.FromName(name)!)
            .HasColumnName("scope")
            .HasMaxLength(50)
            .IsRequired();

        builder.Property(permission => permission.DescriptionAr).HasColumnName("description_ar").HasColumnType("text");
        builder.Property(permission => permission.DescriptionEn).HasColumnName("description_en").HasColumnType("text");

        builder.Property(permission => permission.CreatedAtUtc)
            .HasColumnName("created_at")
            .HasDefaultValueSql("NOW()")
            .ValueGeneratedOnAdd()
            .IsRequired();
        builder.Property(permission => permission.CreatedBy).HasColumnName("created_by").IsRequired();
    }
}
