using Atlas.Modules.Identity.Domain.Users;
using Atlas.Modules.Identity.Domain.ValueObjects;
using Atlas.Persistence.PostgreSql.Configurations;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Atlas.Modules.Identity.Infrastructure.Configurations;

internal sealed class UserConfiguration : EntityTypeConfiguration<User>
{
    protected override void ConfigureEntity(EntityTypeBuilder<User> builder)
    {
        builder.ToTable("users");

        builder.HasKey(user => user.Id);
        builder.Property(user => user.Id).HasColumnName("user_id").ValueGeneratedOnAdd();

        builder.Property(user => user.TenantId).HasColumnName("tenant_id").IsRequired();

        builder.Property(user => user.Username)
            .HasConversion(username => username.Value, value => Username.Create(value))
            .HasColumnName("username")
            .HasMaxLength(Username.MaxLength)
            .IsRequired();
        builder.HasIndex(user => new { user.TenantId, user.Username })
            .IsUnique()
            .HasDatabaseName("uq_users_username");

        builder.Property(user => user.Email)
            .HasConversion(email => email!.Value, value => Email.Create(value))
            .HasColumnName("email")
            .HasMaxLength(Email.MaxLength);
        builder.HasIndex(user => new { user.TenantId, user.Email })
            .IsUnique()
            .HasFilter("email IS NOT NULL AND is_deleted = FALSE")
            .HasDatabaseName("uq_users_tenant_email");

        builder.Property(user => user.PasswordHash)
            .HasConversion(hash => hash.Value, value => PasswordHash.Create(value))
            .HasColumnName("password_hash")
            .HasMaxLength(PasswordHash.MaxLength)
            .IsRequired();

        builder.Property(user => user.Phone).HasColumnName("phone").HasMaxLength(User.PhoneMaxLength);
        builder.Property(user => user.FullNameAr).HasColumnName("full_name_ar").HasMaxLength(User.FullNameMaxLength);
        builder.Property(user => user.FullNameEn).HasColumnName("full_name_en").HasMaxLength(User.FullNameMaxLength);
        builder.Property(user => user.EmployeeId).HasColumnName("employee_id");
        builder.Property(user => user.MfaEnabled).HasColumnName("mfa_enabled").IsRequired();
        builder.Property(user => user.MfaSecret).HasColumnName("mfa_secret").HasMaxLength(User.MfaSecretMaxLength);
        builder.Property(user => user.FailedLoginAttempts).HasColumnName("failed_login_attempts").IsRequired();
        builder.Property(user => user.LockoutUntilUtc).HasColumnName("lockout_until");
        builder.Property(user => user.LastLoginAtUtc).HasColumnName("last_login_at");
        builder.Property(user => user.LastLoginIp).HasColumnName("last_login_ip").HasMaxLength(User.IpMaxLength);
        builder.Property(user => user.PasswordChangedAtUtc).HasColumnName("password_changed_at");
        builder.Property(user => user.ForcePasswordChange).HasColumnName("force_password_change").IsRequired();
        builder.Property(user => user.EntityVersion).HasColumnName("entity_version").IsRequired();
        builder.Property(user => user.IsActive).HasColumnName("is_active").IsRequired();

        builder.Property(user => user.IsDeleted).HasColumnName("is_deleted").IsRequired();
        builder.Property(user => user.DeletedAtUtc).HasColumnName("deleted_at");
        builder.Property(user => user.DeletedBy).HasColumnName("deleted_by");
        builder.Property(user => user.CreatedAtUtc).HasColumnName("created_at").IsRequired();
        builder.Property(user => user.CreatedBy).HasColumnName("created_by").IsRequired();
        builder.Property(user => user.UpdatedAtUtc).HasColumnName("updated_at");
        builder.Property(user => user.UpdatedBy).HasColumnName("updated_by");
    }
}
