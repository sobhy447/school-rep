using Atlas.Modules.Tenancy.Domain.Companies;
using Atlas.Persistence.PostgreSql.Configurations;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace Atlas.Modules.Tenancy.Infrastructure.Configurations;

internal sealed class CompanyConfiguration : EntityTypeConfiguration<Company>
{
    protected override void ConfigureEntity(EntityTypeBuilder<Company> builder)
    {
        builder.ToTable("companies");

        builder.HasKey(company => company.Id);
        builder.Property(company => company.Id).HasColumnName("company_id").ValueGeneratedOnAdd();

        builder.Property(company => company.TenantId).HasColumnName("tenant_id").IsRequired();

        builder.Property(company => company.CompanyCode)
            .HasColumnName("company_code")
            .HasMaxLength(Company.CodeMaxLength)
            .IsRequired();
        builder.HasIndex(company => new { company.TenantId, company.CompanyCode })
            .IsUnique()
            .HasDatabaseName("uq_companies_code");

        builder.OwnsOne(company => company.Name, name =>
        {
            name.Property(value => value.Arabic)
                .HasColumnName("company_name_ar").HasMaxLength(500).IsRequired();
            name.Property(value => value.English)
                .HasColumnName("company_name_en").HasMaxLength(500).IsRequired();
        });
        builder.Navigation(company => company.Name).IsRequired();

        builder.Property(company => company.TaxNumber).HasColumnName("tax_number").HasMaxLength(Company.TaxNumberMaxLength);
        builder.Property(company => company.RegistrationNo).HasColumnName("registration_no").HasMaxLength(Company.RegistrationNoMaxLength);
        builder.Property(company => company.BaseCurrencyId).HasColumnName("base_currency_id").IsRequired();

        builder.OwnsOne(company => company.Address, address =>
        {
            address.Property(value => value.Line1).HasColumnName("address_line1").HasMaxLength(500);
            address.Property(value => value.Line2).HasColumnName("address_line2").HasMaxLength(500);
            address.Property(value => value.City).HasColumnName("city").HasMaxLength(200);
            address.Property(value => value.CountryCode).HasColumnName("country_code").HasMaxLength(3).IsRequired();
        });
        builder.Navigation(company => company.Address).IsRequired();

        builder.Property(company => company.Phone).HasColumnName("phone").HasMaxLength(Company.PhoneMaxLength);
        builder.Property(company => company.Email).HasColumnName("email").HasMaxLength(Company.EmailMaxLength);
        builder.Property(company => company.LogoUrl).HasColumnName("logo_url").HasMaxLength(Company.LogoUrlMaxLength);
        builder.Property(company => company.FiscalYearStartMonth)
            .HasColumnName("fiscal_year_start_month").HasColumnType("smallint").IsRequired();
        builder.Property(company => company.IsActive).HasColumnName("is_active").IsRequired();
        builder.Property(company => company.EntityVersion).HasColumnName("entity_version").IsRequired();

        builder.Property(company => company.IsDeleted).HasColumnName("is_deleted").IsRequired();
        builder.Property(company => company.DeletedAtUtc).HasColumnName("deleted_at");
        builder.Property(company => company.DeletedBy).HasColumnName("deleted_by");
        builder.Property(company => company.CreatedAtUtc).HasColumnName("created_at").IsRequired();
        builder.Property(company => company.CreatedBy).HasColumnName("created_by").IsRequired();
        builder.Property(company => company.UpdatedAtUtc).HasColumnName("updated_at");
        builder.Property(company => company.UpdatedBy).HasColumnName("updated_by");
    }
}
