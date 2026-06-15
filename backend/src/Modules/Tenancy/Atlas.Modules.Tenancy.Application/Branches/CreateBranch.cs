using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Modules.Tenancy.Application.Common;
using Atlas.Modules.Tenancy.Contracts.Branches;
using Atlas.Modules.Tenancy.Domain;
using Atlas.Modules.Tenancy.Domain.Branches;
using Atlas.Modules.Tenancy.Domain.Companies;
using Atlas.Modules.Tenancy.Domain.ValueObjects;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;
using FluentValidation;

namespace Atlas.Modules.Tenancy.Application.Branches;

public sealed record CreateBranchCommand(
    long CompanyId,
    string BranchCode,
    string NameAr,
    string NameEn,
    string? Address,
    string? Phone,
    bool IsMainBranch,
    long PerformedBy) : ICommand<BranchResponse>;

internal sealed class CreateBranchCommandValidator : AbstractValidator<CreateBranchCommand>
{
    public CreateBranchCommandValidator()
    {
        RuleFor(command => command.CompanyId).GreaterThan(0);
        RuleFor(command => command.BranchCode).NotEmpty().MaximumLength(Branch.CodeMaxLength);
        RuleFor(command => command.NameAr).NotEmpty().MaximumLength(LocalizedName.MaxLength);
        RuleFor(command => command.NameEn).NotEmpty().MaximumLength(LocalizedName.MaxLength);
        RuleFor(command => command.Address).MaximumLength(Branch.AddressMaxLength);
        RuleFor(command => command.Phone).MaximumLength(Branch.PhoneMaxLength);
        RuleFor(command => command.PerformedBy).GreaterThan(0);
    }
}

public sealed class CreateBranchCommandHandler(
    ITenantContext tenantContext,
    ICompanyRepository companies,
    IBranchRepository branches,
    IUnitOfWork unitOfWork) : ICommandHandler<CreateBranchCommand, BranchResponse>
{
    public async Task<Result<BranchResponse>> Handle(CreateBranchCommand command, CancellationToken cancellationToken)
    {
        long tenantId = tenantContext.TenantId;

        Company? company = await companies.GetByIdAsync(command.CompanyId, cancellationToken);
        if (company is null || company.TenantId != tenantId)
        {
            return Result.Failure<BranchResponse>(TenancyErrors.CompanyNotFound(command.CompanyId));
        }

        if (await branches.ExistsByCodeAsync(tenantId, command.CompanyId, command.BranchCode, cancellationToken))
        {
            return Result.Failure<BranchResponse>(TenancyErrors.BranchCodeAlreadyExists(command.BranchCode));
        }

        LocalizedName name = LocalizedName.Create(command.NameAr, command.NameEn);

        Branch branch = Branch.Create(
            command.CompanyId,
            command.BranchCode,
            name,
            command.Address,
            command.Phone,
            command.IsMainBranch,
            command.PerformedBy);

        branch.SetTenant(tenantId);
        branch.RegisterCreatedEvent();

        await branches.AddAsync(branch, cancellationToken);
        await unitOfWork.SaveChangesAsync(cancellationToken);

        return TenancyMapper.ToResponse(branch);
    }
}
