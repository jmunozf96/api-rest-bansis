CREATE DATABASE Bansis_pru;

CREATE TABLE SIS_USUARIOS
(
    id             int identity (1,1) not null,
    nombre         varchar(100)       not null,
    apellido       varchar(250),
    correo         varchar(250),
    nick           varchar(100)       not null,
    contraseña     varchar(150)       not NULL,
    avatar         varchar(250),
    descripcion    text,
    created_at     datetime default null,
    updated_at     datetime default null,
    remember_token varchar(255),
    estado         bit default 1,
    CONSTRAINT pk_idUsuario PRIMARY KEY (id)
)

CREATE TABLE EMP_DESTINO
(
    id          int identity (1,1) not null,
    descripcion varchar(100)       not null,
    continente  varchar(100)       not null,
    created_at  datetime default null,
    updated_at  datetime default null,
    estado      bit      default 1,
    CONSTRAINT pk_destino PRIMARY KEY (id)
)

CREATE TABLE EMP_TIPO_CAJA
(
    id          int identity (1,1) not null,
    descripcion varchar(150)       not null,
    created_at  datetime default null,
    updated_at  datetime default null,
    estado      bit      default 1,
    CONSTRAINT pk_tipoCaja PRIMARY KEY (id)
)

CREATE TABLE EMP_DISTRIBUIDOR
(
    id          int identity (1,1) not null,
    descripcion varchar(150)       not null,
    created_at  datetime default null,
    updated_at  datetime default null,
    estado      bit      default 1,
    CONSTRAINT pk_distribuidor PRIMARY KEY (id)
)

CREATE TABLE EMP_CAJAS
(
    id               int identity (1,1) not null,
    descripcion      varchar(250)       not null,
    peso_max         float    default 0,
    peso_min         float    default 0 not null,
    peso_standard    float    default 0 not null,
    id_destino       int                not null,
    id_tipoCaja      int                not null,
    id_distrib       int                not null,
    id_codAllweights int,
    created_at       datetime default null,
    updated_at       datetime default null,
    estado           bit      default 1,
    CONSTRAINT pk_caja PRIMARY KEY (id),
    CONSTRAINT fk_destino_caja FOREIGN KEY (id_destino) REFERENCES EMP_DESTINO (id),
    CONSTRAINT fk_tipo_caja FOREIGN KEY (id_tipoCaja) REFERENCES EMP_TIPO_CAJA (id),
    CONSTRAINT fk_distrib_caja FOREIGN KEY (id_distrib) REFERENCES EMP_DISTRIBUIDOR (id)
)

CREATE TABLE EMP_COD_COORP
(
    id          int identity (1,1) not null,
    descripcion varchar(200)       not null,
    id_caja     int                not null,
    created_at  datetime default null,
    updated_at  datetime default null,
    estado      bit      default 1,
    CONSTRAINT pk_codCoorp PRIMARY KEY (id),
    CONSTRAINT fk_coorp_caja FOREIGN KEY (id_caja) REFERENCES EMP_CAJAS (id)
)

CREATE TABLE EMP_VAPOR
(
    id          int identity (1,1) not null,
    descripcion varchar(150)       not null,
    created_at  datetime default null,
    updated_at  datetime default null,
    estado      bit      default 1,
    CONSTRAINT pk_vapor PRIMARY KEY (id)
)

CREATE TABLE EMP_LIQUIDACION
(
    id          int identity (1,1) not null,
    fecha       date               not null,
    idsemana    int                not null,
    idhacienda  int                not null,
    lq_hora     int,
    lq_corter   int,
    lq_arrum    int,
    lq_garru    int,
    lq_horasc   int,
    lq_area     float,
    lq_areaefe  float,
    lq_areapcn  float,
    lq_inicio   int,
    lq_fin      int,
    lq_hombres  int,
    lq_embalad  int,
    lq_tpcomid  int,
    lq_tpmater  int,
    lq_tpffrut  int,
    lq_tpmecan  int,
    lq_tpotros  int,
    lq_seccion  varchar(300),
    lq_observ   text,
    lq_color1   int,
    lq_color2   int,
    lq_color3   int,
    lq_color4   int,
    lq_color5   int,
    lq_color6   int,
    lq_racim1   int,
    lq_racim2   int,
    lq_racim3   int,
    lq_racim4   int,
    lq_racim5   int,
    lq_racim6   int,
    lq_racimos  int,
    lq_recusad  int,
    lq_proces   int,
    lq_rhhomb   int,
    lq_rhcort   int,
    lq_peso     float,
    lq_sumpeso  float,
    lq_sumrecu  float,
    lq_sumproc  float,
    lq_sumemp   float,
    lq_ratio    float,
    lq_ratiopc  float,
    lq_merma    float,
    lq_mermapc  float,
    lq_calib2   float,
    lq_manos    float,
    lq_cajas    int,
    lq_horase   int,
    lq_chemp    int,
    lq_chhomb   int,
    lq_chembal  int,
    merma1      float              not null,
    merma2      float              not null,
    peso_Total  float              not null,
    ratio_pc    float,
    ratio       float,
    total_Cajas int                not null,
    usuario     int,
    created_at  datetime default null,
    updated_at  datetime default null,
    estado      bit      default 1,
    CONSTRAINT pk_liquidacion PRIMARY KEY (id)
)

CREATE TABLE EMP_DET_LIQUIDACION
(
    id              int identity (1,1) not null,
    id_liquid       int                not null,
    id_codCorp      int                not null,
    id_vapor        int,
    cantidad        int                not null,
    pesoTotal       float              not null,
    tara            float,
    cant_pesadas    int                not null,
    despachadas     int,
    saldo           int,
    pendiente       bit,
    liquidada       bit,
    embaladores     int,
    pesoProm_emb    int,
    hora_ultcajapes time,
    tipo_transp     varchar(80),
    cod_transp      varchar(150),
    hcierre         time,
    hdespacho       time,
    usuario         int,
    created_at      datetime default null,
    updated_at      datetime default null,
    estado          bit      default 1,
    CONSTRAINT pk_detalle_liquidacion PRIMARY KEY (id),
    CONSTRAINT fk_liquid_detalle FOREIGN KEY (id_liquid) REFERENCES EMP_LIQUIDACION (id),
    CONSTRAINT fk_vapor_liquid FOREIGN KEY (id_vapor) references EMP_VAPOR (id),
    CONSTRAINT fk_codCorp_detliquid FOREIGN KEY (id_codCorp) REFERENCES EMP_COD_COORP (id)
)

CREATE TABLE BOD_BODEGAS
(
    id          int identity (1,1) not null,
    idhacienda  int                not null,
    descripcion varchar(150)       not null,
    created_at  datetime default null,
    updated_at  datetime default null,
    estado      bit      default 1,
    CONSTRAINT PK_BODEGA PRIMARY KEY (id),
)

CREATE TABLE BOD_GRUPOS
(
    id          int identity (1,1) not null,
    descripcion varchar(150)       not null,
    created_at  datetime default null,
    updated_at  datetime default null,
    estado      bit      default 1,
    CONSTRAINT PK_GRUPO PRIMARY KEY (id),
)

CREATE TABLE BOD_MATERIALES
(
    id          int identity (1,1) not null,
    idbodega    int                not null,
    idgrupo     int                not null,
    descripcion varchar(150)       not null,
    created_at  datetime default null,
    updated_at  datetime default null,
    estado      bit      default 1,
    CONSTRAINT PK_MATERIAL PRIMARY KEY (id),
    CONSTRAINT FK_MAT_BOD FOREIGN KEY (idbodega) REFERENCES BOD_BODEGAS (id),
    CONSTRAINT FK_MAT_GRP FOREIGN KEY (idgrupo) REFERENCES BOD_GRUPOS (id)
)

CREATE TABLE HAC_EMPLEADOS
(
    id         int identity (1,1) not null,
    codigo     int                not null,
    idhacienda int                not null,
    nombre1    varchar(150)       not null,
    nombre2    varchar(150),
    apellido1  varchar(150)       not null,
    apellido2  varchar(150),
    nombres    varchar(250)       not null,
    image      varchar(300),
    created_at datetime default null,
    updated_at datetime default null,
    estado     bit      default 1,
    CONSTRAINT PK_EMPLEADO PRIMARY KEY (id)
)

CREATE TABLE BOD_EGRESOS
(
    id         int identity (1,1)         not null,
    idcalendar int                        not null,
    idempleado int                        not null,
    fecha      date     default getdate() not null,
    created_at datetime default null,
    updated_at datetime default null,
    estado     bit      default 1,
    CONSTRAINT PK_EGRESO PRIMARY KEY (id),
    CONSTRAINT FK_EGR_EMP FOREIGN KEY (idempleado) REFERENCES HAC_EMPLEADOS (id)
)

CREATE TABLE BOD_DET_EGRESOS
(
    id           int identity (1,1)         not null,
    idegreso     int                        not null,
    idmaterial   int                        not null,
    fecha_salida date     default getdate() not null,
    cantidad     int                        not null,
    created_at   datetime default null,
    updated_at   datetime default null,
    estado       bit      default 1,
    CHECK (cantidad > 0),
    CONSTRAINT PK_DET_EGRESO PRIMARY KEY (id),
    CONSTRAINT FK_EGRESO FOREIGN KEY (idegreso) REFERENCES BOD_EGRESOS (id),
    CONSTRAINT FK_DET_EGR_MATERIAL FOREIGN KEY (idmaterial) REFERENCES BOD_MATERIALES (id)
)

CREATE TABLE HAC_ENF_REELEVOS
(
    id         int identity (1,1) not null,
    idempleado int                not null,
    created_at datetime default null,
    updated_at datetime default null,
    estado     bit      default 1,
    CONSTRAINT PK_REELEVO PRIMARY KEY (id),
    CONSTRAINT FK_RLV_EMP FOREIGN KEY (idempleado) REFERENCES HAC_EMPLEADOS (id)
)

CREATE TABLE HAC_LABORES
(
    id          int identity (1,1) not null,
    descripcion varchar(150)       not null,
    created_at  datetime default null,
    updated_at  datetime default null,
    estado      bit      default 1,
    CONSTRAINT PK_LABOR PRIMARY KEY (id)
)

CREATE TABLE HAC_EMP_LAB
(
    id         int identity (1,1) not null,
    idempleado int                not null,
    idlabor    int                not null,
    created_at datetime default null,
    updated_at datetime default null,
    estado     bit      default 1,
    CONSTRAINT PK_EMP_LABOR PRIMARY KEY (id),
    CONSTRAINT FK_EMPLEADO FOREIGN KEY (idempleado) REFERENCES HAC_EMPLEADOS (id),
    CONSTRAINT FK_LABOR FOREIGN KEY (idlabor) REFERENCES HAC_LABORES (id)
)

CREATE TABLE HAC_LOTES
(
    id          int identity (1,1) not null,
    idhacienda  int                not null,
    descripcion varchar(150)       not null,
    created_at  datetime default null,
    updated_at  datetime default null,
    estado      bit      default 1,
    CONSTRAINT PK_LOTE PRIMARY KEY (id)
)

CREATE TABLE HAC_SECCIONES
(
    id             int identity (1,1)         not null,
    fecha_apertura date     default getdate() not null,
    idemplab       int                        not null,
    idlote         int                        not null,
    has            float,
    created_at     datetime default null,
    updated_at     datetime default null,
    estado         bit      default 1,
    CONSTRAINT PK_SECCION PRIMARY KEY (id),
    CONSTRAINT FK_SEC_EMPLAB FOREIGN KEY (idemplab) REFERENCES HAC_EMP_LAB (id),
    CONSTRAINT FK_SE_LOTE FOREIGN KEY (idlote) REFERENCES HAC_LOTES (id)
)

CREATE TABLE HAC_ENFUNDES
(
    id         int identity (1,1)         not null,
    idclaendar int                        not null,
    idhacienda int                        not null,
    fecha      date     default getdate() not null,
    cinta_pre  int                        not null,
    cinta_fut  int                        not null,
    presente   bit      default 1         not null,
    futuro     bit      default 0,
    cerrado    bit      default 0,
    created_at datetime default null,
    updated_at datetime default null,
    estado     bit      default 1,
    CONSTRAINT PK_ENFUNDE PRIMARY KEY (id)
)

CREATE TABLE HAC_DET_ENFUNDES
(
    id         int identity (1,1) not null,
    idenfunde  int                not null,
    idseccion  int                not null,
    cant_pre   int,
    cant_fut   int,
    cant_desb  int,
    created_at datetime default null,
    updated_at datetime default null,
    estado     bit      default 1,
    CONSTRAINT PK_DET_ENFUNDE PRIMARY KEY (id),
    CONSTRAINT FK_ENFUNDE FOREIGN KEY (idenfunde) REFERENCES HAC_ENFUNDES (id),
    CONSTRAINT FK_ENF_SECCION FOREIGN KEY (idseccion) REFERENCES HAC_SECCIONES (id)
)

CREATE TABLE HAC_INV_SEC_ENFUNDE
(
    id         int identity (1,1) not null,
    idcalendar int                not null,
    idseccion  int                not null,
    idmaterial int                not null,
    sld_ini    int,
    tot_egre   int,
    tot_enf    int,
    sld_fin    int,
    created_at datetime default null,
    updated_at datetime default null,
    estado     bit      default 1,
    CONSTRAINT PK_INV_SECCION PRIMARY KEY (id),
    CONSTRAINT FK_SECCION FOREIGN KEY (idseccion) REFERENCES HAC_SECCIONES (id),
    CONSTRAINT FK_MATERIAL FOREIGN KEY (idmaterial) REFERENCES BOD_MATERIALES (ID)
)
