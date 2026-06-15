using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.Modules.Tenancy.Application.Common;
using Atlas.Modules.Tenancy.Contracts.Companies;
using Atlas.Modules.Tenancy.Domain.Companies;
using Atlas.MultiTenancy.Abstractions;
using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Tenancy.Application.Companies;

public sealed record GetCompaniesByTenantQuery : IQuery<IReadOnlyList<CompanyResponse>>;

public sealed class GetCompaniesByTenantQueryHandler(ITenantContext tenantContext, ICompanyRepository companies)
    : IQueryHandler<GetCompaniesByTenantQuery, IReadOnlyList<CompanyResponse>>
{
    public async Task<Result<IReadOnlyList<CompanyResponse>>> Handle(
        GetCompaniesByTenantQuery query,
        CancellationToken cancellationToken)
    {
        IReadOnlyList<Company> result = await companies.ListByTenantAsync(tenantContext.TenantId, cancellationToken);

        IReadOnlyList<CompanyResponse> response = result.Select(TenancyMapper.ToResponse).ToList();
        return Result.Success(response);
    }
}
