using Atlas.BuildingBlocks.Application.Messaging;
using Atlas.Modules.Tenancy.Application.Common;
using Atlas.Modules.Tenancy.Contracts.Companies;
using Atlas.Modules.Tenancy.Domain;
using Atlas.Modules.Tenancy.Domain.Companies;
using Atlas.SharedKernel.Results;

namespace Atlas.Modules.Tenancy.Application.Companies;

public sealed record GetCompanyByIdQuery(long CompanyId) : IQuery<CompanyResponse>;

public sealed class GetCompanyByIdQueryHandler(ICompanyRepository companies)
    : IQueryHandler<GetCompanyByIdQuery, CompanyResponse>
{
    public async Task<Result<CompanyResponse>> Handle(GetCompanyByIdQuery query, CancellationToken cancellationToken)
    {
        Company? company = await companies.GetByIdAsync(query.CompanyId, cancellationToken);

        return company is null
            ? Result.Failure<CompanyResponse>(TenancyErrors.CompanyNotFound(query.CompanyId))
            : TenancyMapper.ToResponse(company);
    }
}
