using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Tenancy.Application.Common;
using Atlas.Modules.Tenancy.Contracts.Companies;
using Atlas.Modules.Tenancy.Domain;
using Atlas.Modules.Tenancy.Domain.Companies;
using Atlas.Modules.Tenancy.Domain.ValueObjects;
using Atlas.SharedKernel.Results;
using FluentValidation;

namespace Atlas.Modules.Tenancy.Application.Companies;

public sealed record UpdateCompanyCommand(
    long CompanyId,
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

internal sealed class UpdateCompanyCommandValidator : AbstractValidator<UpdateCompanyCommand>
{
    public UpdateCompanyCommandValidator()
    {
        RuleFor(command => command.CompanyId).GreaterThan(0);
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

public sealed class UpdateCompanyCommandHandler(ICompanyRepository companies, IUnitOfWork unitOfWork)
    : ICommandHandler<UpdateCompanyCommand, CompanyResponse>
{
    public async Task<Result<CompanyResponse>> Handle(UpdateCompanyCommand command, CancellationToken cancellationToken)
    {
        Company? company = await companies.GetByIdAsync(command.CompanyId, cancellationToken);
        if (company is null)
        {
            return Result.Failure<CompanyResponse>(TenancyErrors.CompanyNotFound(command.CompanyId));
        }

        LocalizedName name = LocalizedName.Create(command.NameAr, command.NameEn);
        Address address = Address.Create(command.AddressLine1, command.AddressLine2, command.City, command.CountryCode);

        company.UpdateProfile(
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

        companies.Update(company);
        await unitOfWork.SaveChangesAsync(cancellationToken);

        return TenancyMapper.ToResponse(company);
    }
}
