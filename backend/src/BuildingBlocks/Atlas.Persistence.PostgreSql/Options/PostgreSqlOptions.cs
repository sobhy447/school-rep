namespace Atlas.Persistence.PostgreSql.Options;

public sealed class PostgreSqlOptions
{
    public const string SectionName = "PostgreSql";

    public string ConnectionString { get; set; } = string.Empty;

    public string Schema { get; set; } = "atlas";

    public string MigrationsHistoryTable { get; set; } = "__ef_migrations_history";

    public int MaxRetryCount { get; set; } = 3;

    public TimeSpan MaxRetryDelay { get; set; } = TimeSpan.FromSeconds(10);

    public int CommandTimeoutSeconds { get; set; } = 30;

    public bool EnableSensitiveDataLogging { get; set; }

    public bool EnableDetailedErrors { get; set; }
}
