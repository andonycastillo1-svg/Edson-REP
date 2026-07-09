public function up(): void
{
    Schema::table('inventarios', function (Blueprint $table) {
        $table->enum('stock_tipo', ['nuevo', 'usado'])
            ->default('nuevo')
            ->after('cantidad');

        $table->integer('vida_util_restante_meses')
            ->nullable()
            ->after('stock_tipo');
    });
}

public function down(): void
{
    Schema::table('inventarios', function (Blueprint $table) {
        $table->dropColumn(['stock_tipo', 'vida_util_restante_meses']);
    });
}