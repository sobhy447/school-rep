using Atlas.Modules.Tenancy.Domain.Branches;
using Atlas.Persistence.PostgreSql.Configurations;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Atlas.Modules.Tenancy.Infrastructure.Configurations;

internal sealed class BranchConfiguration : EntityTypeConfiguration<Branch>
{
    protected override void ConfigureEntity(EntityTypeBuilder<Branch> builder)
    {
        builder.ToTable("branches");

        builder.HasKey(branch => branch.Id);
        builder.Property(branch => branch.Id).HasColumnName("branch_id").ValueGeneratedOnAdd();

        builder.Property(branch => branch.TenantId).HasColumnName("tenant_id").IsRequired();
        builder.Property(branch => branch.CompanyId).HasColumnName("company_id").IsRequired();

        builder.Property(branch => branch.BranchCode)
            .HasColumnName("branch_code")
            .HasMaxLength(Branch.CodeMaxLength)
            .IsRequired();
        builder.HasIndex(branch => new { branch.TenantId, branch.CompanyId, branch.BranchCode })
            .IsUnique()
            .HasDatabaseName("uq_branches_code");

        builder.OwnsOne(branch => branch.Name, name =>
        {
            name.Property(value => value.Arabic)
                .HasColumnName("branch_name_ar").HasMaxLength(500).IsRequired();
            name.Property(value => value.English)
                .HasColumnName("branch_name_en").HasMaxLength(500).IsRequired();
        });
        builder.Navigation(branch => branch.Name).IsRequired();

        builder.Property(branch => branch.Address).HasColumnName("address").HasMaxLength(Branch.AddressMaxLength);
        builder.Property(branch => branch.Phone).HasColumnName("phone").HasMaxLength(Branch.PhoneMaxLength);
        builder.Property(branch => branch.IsMainBranch).HasColumnName("is_main_branch").IsRequired();
        builder.Property(branch => branch.IsActive).HasColumnName("is_active").IsRequired();
        builder.Property(branch => branch.EntityVersion).HasColumnName("entity_version").IsRequired();

        builder.Property(branch => branch.IsDeleted).HasColumnName("is_deleted").IsRequired();
        builder.Property(branch => branch.DeletedAtUtc).HasColumnName("deleted_at");
        builder.Property(branch => branch.DeletedBy).HasColumnName("deleted_by");
        builder.Property(branch => branch.CreatedAtUtc).HasColumnName("created_at").IsRequired();
        builder.Property(branch => branch.CreatedBy).HasColumnName("created_by").IsRequired();
        builder.Property(branch => branch.UpdatedAtUtc).HasColumnName("updated_at");
        builder.Property(branch => branch.UpdatedBy).HasColumnName("updated_by");
    }
}
