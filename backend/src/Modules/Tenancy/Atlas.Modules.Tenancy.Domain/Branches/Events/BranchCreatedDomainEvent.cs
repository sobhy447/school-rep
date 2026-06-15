using Atlas.SharedKernel.Events;

namespace Atlas.Modules.Tenancy.Domain.Branches.Events;

public sealed record BranchCreatedDomainEvent(long TenantId, long CompanyId, string BranchCode) : DomainEvent;
