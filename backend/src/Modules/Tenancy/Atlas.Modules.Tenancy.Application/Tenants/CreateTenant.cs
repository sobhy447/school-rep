using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Tenancy.Application.Common;
using Atlas.Modules.Tenancy.Contracts.Tenants;
using Atlas.Modules.Tenancy.Domain;
using Atlas.Modules.Tenancy.Domain.Tenants;
using Atlas.Modules.Tenancy.Domain.ValueObjects;
using Atlas.SharedKernel.Results;
using FluentValidation;

namespace Atlas.Modules.Tenancy.Application.Tenants;

public sealed record CreateTenantCommand(
    string TenantCode,
    string NameAr,
    string NameEn,
    long BaseCurrencyId,
    int MaxCompanies,
    int MaxUsers,
    string DefaultLanguage,
    string TimeZone,
    long PerformedBy) : ICommand<TenantResponse>;

internal sealed class CreateTenantCommandValidator : AbstractValidator<CreateTenantCommand>
{
    public CreateTenantCommandValidator()
    {
        RuleFor(command => command.TenantCode).NotEmpty().MaximumLength(Tenant.CodeMaxLength);
        RuleFor(command => command.NameAr).NotEmpty().MaximumLength(LocalizedName.MaxLength);
        RuleFor(command => command.NameEn).NotEmpty().MaximumLength(LocalizedName.MaxLength);
        RuleFor(command => command.BaseCurrencyId).GreaterThan(0);
        RuleFor(command => command.MaxCompanies).GreaterThanOrEqualTo(1);
        RuleFor(command => command.MaxUsers).GreaterThanOrEqualTo(1);
        RuleFor(command => command.DefaultLanguage).NotEmpty().Must(language => language is "ar" or "en")
            .WithMessage("DefaultLanguage must be 'ar' or 'en'.");
        RuleFor(command => command.TimeZone).NotEmpty().MaximumLength(Tenant.TimeZoneMaxLength);
        RuleFor(command => command.PerformedBy).GreaterThan(0);
    }
}

public sealed class CreateTenantCommandHandler(ITenantRepository tenants, IUnitOfWork unitOfWork)
    : ICommandHandler<CreateTenantCommand, TenantResponse>
{
    public async Task<Result<TenantResponse>> Handle(CreateTenantCommand command, CancellationToken cancellationToken)
    {
        if (await tenants.ExistsByCodeAsync(command.TenantCode, cancellationToken))
        {
            return Result.Failure<TenantResponse>(TenancyErrors.TenantCodeAlreadyExists(command.TenantCode));
        }

        LocalizedName name = LocalizedName.Create(command.NameAr, command.NameEn);

        Tenant tenant = Tenant.Create(
            command.TenantCode,
            name,
            command.BaseCurrencyId,
            command.MaxCompanies,
            command.MaxUsers,
            command.DefaultLanguage,
            command.TimeZone,
            command.PerformedBy);

        await tenants.AddAsync(tenant, cancellationToken);
        await unitOfWork.SaveChangesAsync(cancellationToken);

        return TenancyMapper.ToResponse(tenant);
    }
}
