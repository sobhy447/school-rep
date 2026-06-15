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

public sealed record UpdateTenantCommand(
    long TenantId,
    string NameAr,
    string NameEn,
    int MaxCompanies,
    int MaxUsers,
    string DefaultLanguage,
    string TimeZone,
    string SubscriptionStatus,
    long PerformedBy) : ICommand<TenantResponse>;

internal sealed class UpdateTenantCommandValidator : AbstractValidator<UpdateTenantCommand>
{
    public UpdateTenantCommandValidator()
    {
        RuleFor(command => command.TenantId).GreaterThan(0);
        RuleFor(command => command.NameAr).NotEmpty().MaximumLength(LocalizedName.MaxLength);
        RuleFor(command => command.NameEn).NotEmpty().MaximumLength(LocalizedName.MaxLength);
        RuleFor(command => command.MaxCompanies).GreaterThanOrEqualTo(1);
        RuleFor(command => command.MaxUsers).GreaterThanOrEqualTo(1);
        RuleFor(command => command.DefaultLanguage).NotEmpty().Must(language => language is "ar" or "en")
            .WithMessage("DefaultLanguage must be 'ar' or 'en'.");
        RuleFor(command => command.TimeZone).NotEmpty().MaximumLength(Tenant.TimeZoneMaxLength);
        RuleFor(command => command.SubscriptionStatus).NotEmpty();
        RuleFor(command => command.PerformedBy).GreaterThan(0);
    }
}

public sealed class UpdateTenantCommandHandler(ITenantRepository tenants, IUnitOfWork unitOfWork)
    : ICommandHandler<UpdateTenantCommand, TenantResponse>
{
    public async Task<Result<TenantResponse>> Handle(UpdateTenantCommand command, CancellationToken cancellationToken)
    {
        Tenant? tenant = await tenants.GetByIdAsync(command.TenantId, cancellationToken);
        if (tenant is null)
        {
            return Result.Failure<TenantResponse>(TenancyErrors.TenantNotFound(command.TenantId));
        }

        SubscriptionStatus? status = SubscriptionStatus.FromName(command.SubscriptionStatus);
        if (status is null)
        {
            return Result.Failure<TenantResponse>(TenancyErrors.SubscriptionStatusInvalid(command.SubscriptionStatus));
        }

        LocalizedName name = LocalizedName.Create(command.NameAr, command.NameEn);

        tenant.UpdateProfile(
            name,
            command.MaxCompanies,
            command.MaxUsers,
            command.DefaultLanguage,
            command.TimeZone,
            command.PerformedBy);

        tenant.ChangeSubscriptionStatus(status, command.PerformedBy);

        tenants.Update(tenant);
        await unitOfWork.SaveChangesAsync(cancellationToken);

        return TenancyMapper.ToResponse(tenant);
    }
}
