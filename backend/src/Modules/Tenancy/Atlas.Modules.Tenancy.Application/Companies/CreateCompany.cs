using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Tenancy.Application.Common;
using Atlas.Modules.Tenancy.Contracts.Companies;
using Atlas.Modules.Tenancy.Domain;
using Atlas.Modules.Tenancy.Domain.Companies;
using Atlas.Modules.Tenancy.Domain.Tenants;
using Atlas.Modules.Tenancy.Domain.ValueObjects;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;
using FluentValidation;

namespace Atlas.Modules.Tenancy.Application.Companies;

public sealed record CreateCompanyCommand(
    string CompanyCode,
    string NameAr,
    string NameEn,
    long BaseCurrencyId,
    string? TaxNumber,
    string? RegistrationNo,
    string? AddressLine1,
    string? AddressLine2,
    string? City,
    string CountryCode,
    string? Phone,
    string? Email,
    string? LogoUrl,
    short FiscalYearStartMonth,
    long PerformedBy) : ICommand<CompanyResponse>;

internal sealed class CreateCompanyCommandValidator : AbstractValidator<CreateCompanyCommand>
{
    public CreateCompanyCommandValidator()
    {
        RuleFor(command => command.CompanyCode).NotEmpty().MaximumLength(Company.CodeMaxLength);
        RuleFor(command => command.NameAr).NotEmpty().MaximumLength(LocalizedName.MaxLength);
        RuleFor(command => command.NameEn).NotEmpty().MaximumLength(LocalizedName.MaxLength);
        RuleFor(command => command.BaseCurrencyId).GreaterThan(0);
        RuleFor(command => command.CountryCode).NotEmpty().MaximumLength(Address.CountryCodeMaxLength);
        RuleFor(command => command.TaxNumber).MaximumLength(Company.TaxNumberMaxLength);
        RuleFor(command => command.RegistrationNo).MaximumLength(Company.RegistrationNoMaxLength);
        RuleFor(command => command.AddressLine1).MaximumLength(Address.LineMaxLength);
        RuleFor(command => command.AddressLine2).MaximumLength(Address.LineMaxLength);
        RuleFor(command => command.City).MaximumLength(Address.CityMaxLength);
        RuleFor(command => command.Phone).MaximumLength(Company.PhoneMaxLength);
        RuleFor(command => command.Email).MaximumLength(Company.EmailMaxLength).EmailAddress()
            .When(command => !string.IsNullOrWhiteSpace(command.Email));
        RuleFor(command => command.LogoUrl).MaximumLength(Company.LogoUrlMaxLength);
        RuleFor(command => command.FiscalYearStartMonth).InclusiveBetween((short)1, (short)12);
        RuleFor(command => command.PerformedBy).GreaterThan(0);
    }
}

public sealed class CreateCompanyCommandHandler(
    ITenantContext tenantContext,
    ITenantRepository tenants,
    ICompanyRepository companies,
    IUnitOfWork unitOfWork) : ICommandHandler<CreateCompanyCommand, CompanyResponse>
{
    public async Task<Result<CompanyResponse>> Handle(CreateCompanyCommand command, CancellationToken cancellationToken)
    {
        long tenantId = tenantContext.TenantId;

        Tenant? tenant = await tenants.GetByIdAsync(tenantId, cancellationToken);
        if (tenant is null)
        {
            return Result.Failure<CompanyResponse>(TenancyErrors.TenantNotFound(tenantId));
        }

        if (await companies.ExistsByCodeAsync(tenantId, command.CompanyCode, cancellationToken))
        {
            return Result.Failure<CompanyResponse>(TenancyErrors.CompanyCodeAlreadyExists(command.CompanyCode));
        }

        int companyCount = await companies.CountByTenantAsync(tenantId, cancellationToken);
        if (companyCount >= tenant.MaxCompanies)
        {
            return Result.Failure<CompanyResponse>(TenancyErrors.CompanyLimitReached(tenant.MaxCompanies));
        }

        LocalizedName name = LocalizedName.Create(command.NameAr, command.NameEn);
        Address address = Address.Create(command.AddressLine1, command.AddressLine2, command.City, command.CountryCode);

        Company company = Company.Create(
            command.CompanyCode,
            name,
            command.BaseCurrencyId,
            address,
            command.FiscalYearStartMonth,
            command.TaxNumber,
            command.RegistrationNo,
            command.Phone,
            command.Email,
            command.LogoUrl,
            command.PerformedBy);

        company.SetTenant(tenantId);
        company.RegisterCreatedEvent();

        await companies.AddAsync(company, cancellationToken);
        await unitOfWork.SaveChangesAsync(cancellationToken);

        return TenancyMapper.ToResponse(company);
    }
}
