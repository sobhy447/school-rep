using Atlas.SharedKernel.Events;

namespace Atlas.Modules.Identity.Domain.Roles.Events;

public sealed record RoleCreatedDomainEvent(long? TenantId, string RoleCode) : DomainEvent;
