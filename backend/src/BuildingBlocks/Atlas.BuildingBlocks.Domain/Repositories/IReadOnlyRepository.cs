using Atlas.SharedKernel.Primitives;

namespace Atlas.BuildingBlocks.Domain.Repositories;

public interface IReadOnlyRepository<TAggregate, in TId>
    where TAggregate : AggregateRoot<TId>
    where TId : notnull
{
    Task<TAggregate?> GetByIdAsync(TId id, CancellationToken cancellationToken = default);

    Task<TAggregate?> FirstOrDefaultAsync(
        ISpecification<TAggregate> specification,
        CancellationToken cancellationToken = default);

    Task<IReadOnlyList<TAggregate>> ListAsync(
        ISpecification<TAggregate> specification,
        CancellationToken cancellationToken = default);

    Task<bool> AnyAsync(
        ISpecification<TAggregate> specification,
        CancellationToken cancellationToken = default);

    Task<int> CountAsync(
        ISpecification<TAggregate> specification,
        CancellationToken cancellationToken = default);
}
