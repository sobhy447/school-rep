using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.Modules.Tenancy.Application.Common;
using Atlas.Modules.Tenancy.Contracts.Branches;
using Atlas.Modules.Tenancy.Domain.Branches;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Tenancy.Application.Branches;

public sealed record GetBranchesByCompanyQuery(long CompanyId) : IQuery<IReadOnlyList<BranchResponse>>;

public sealed class GetBranchesByCompanyQueryHandler(ITenantContext tenantContext, IBranchRepository branches)
    : IQueryHandler<GetBranchesByCompanyQuery, IReadOnlyList<BranchResponse>>
{
    public async Task<Result<IReadOnlyList<BranchResponse>>> Handle(
        GetBranchesByCompanyQuery query,
        CancellationToken cancellationToken)
    {
        IReadOnlyList<Branch> result =
            await branches.ListByCompanyAsync(tenantContext.TenantId, query.CompanyId, cancellationToken);

        IReadOnlyList<BranchResponse> response = result.Select(TenancyMapper.ToResponse).ToList();
        return Result.Success(response);
    }
}
