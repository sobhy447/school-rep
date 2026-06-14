using System.Data.Common;
using System.Globalization;
using Atlas.MultiTenancy.Abstractions;
using Microsoft.EntityFrameworkCore.Diagnostics;

namespace Atlas.Persistence.PostgreSql.Interceptors;

public sealed class TenantSessionInterceptor(ITenantContext tenantContext) : DbConnectionInterceptor
{
    private const string SetTenantSql = "SELECT set_config('app.current_tenant_id', @tenant_id, false)";

    public override void ConnectionOpened(DbConnection connection, ConnectionEndEventData eventData)
    {
        ApplyTenant(connection);
        base.ConnectionOpened(connection, eventData);
    }

    public override async Task ConnectionOpenedAsync(
        DbConnection connection,
        ConnectionEndEventData eventData,
        CancellationToken cancellationToken = default)
    {
        await ApplyTenantAsync(connection, cancellationToken);
        await base.ConnectionOpenedAsync(connection, eventData, cancellationToken);
    }

    private void ApplyTenant(DbConnection connection)
    {
        long tenantId = tenantContext.TenantId;

        using DbCommand command = CreateCommand(connection, tenantId);
        command.ExecuteNonQuery();
    }

    private async Task ApplyTenantAsync(DbConnection connection, CancellationToken cancellationToken)
    {
        long tenantId = tenantContext.TenantId;

        await using DbCommand command = CreateCommand(connection, tenantId);
        await command.ExecuteNonQueryAsync(cancellationToken);
    }

    private static DbCommand CreateCommand(DbConnection connection, long tenantId)
    {
        DbCommand command = connection.CreateCommand();
        command.CommandText = SetTenantSql;

        DbParameter parameter = command.CreateParameter();
        parameter.ParameterName = "tenant_id";
        parameter.Value = tenantId.ToString(CultureInfo.InvariantCulture);
        command.Parameters.Add(parameter);

        return command;
    }
}
