CREATE EXTENSION postgis;



CREATE TABLE tr001_user_group
(
  user_group_id serial NOT NULL,
  user_group_name character varying(64),
  user_group_status boolean DEFAULT false,

  user_group_has_permission_create_rock boolean,
  user_group_has_permission_read_rock boolean,
  user_group_has_permission_update_rock boolean,
  user_group_has_permission_delete_rock boolean,

  user_group_has_permission_create_tag boolean,
  user_group_has_permission_read_tag boolean,
  user_group_has_permission_update_tag boolean,
  user_group_has_permission_delete_tag boolean,

  user_group_has_permission_create_s3 boolean,
  user_group_has_permission_read_s3 boolean,
  user_group_has_permission_update_s3 boolean,
  user_group_has_permission_delete_s3 boolean,

  user_group_has_permission_create_user boolean,
  user_group_has_permission_read_user boolean,
  user_group_has_permission_update_user boolean,
  user_group_has_permission_delete_user boolean,

  user_group_has_permission_create_user_group boolean,
  user_group_has_permission_read_user_group boolean,
  user_group_has_permission_update_user_group boolean,
  user_group_has_permission_delete_user_group boolean,
  CONSTRAINT user_group_pk PRIMARY KEY (user_group_id),
  CONSTRAINT user_group_name_unique UNIQUE (user_group_name)
);



CREATE TABLE tr001_user
(
  user_id serial NOT NULL,
  user_full_name character varying(256),
  user_username character varying(256),
  user_status boolean,
  user_password character varying(128),
  user_group integer,
  CONSTRAINT user_pk PRIMARY KEY (user_id),
  CONSTRAINT user_group_fk FOREIGN KEY (user_group)
    REFERENCES tr001_user_group (user_group_id) MATCH SIMPLE
    ON UPDATE NO ACTION ON DELETE RESTRICT,
  CONSTRAINT user_username_unique UNIQUE (user_username)
);



CREATE TABLE tr001_tag
(
  id serial NOT NULL,
  tag character varying(256),
  CONSTRAINT tag_pk PRIMARY KEY (id),
  CONSTRAINT tag_unique UNIQUE (tag)
);



CREATE TABLE tr001_s3
(
  id serial NOT NULL,
  name character varying(256),
  size integer,
  type character varying(256),
  url character varying(256),
  CONSTRAINT s3_pk PRIMARY KEY (id)
);



CREATE TABLE tr001_rock
(
  id serial NOT NULL,
  col_integer integer,
  col_float double precision,
  col_double double precision,
  col_json json,
  col_bool boolean,
  col_string text,
  col_fk integer,
  col_fk_m integer[] DEFAULT ARRAY[]::integer[],
  col_geometry geometry,
  CONSTRAINT rock_pk PRIMARY KEY (id),
  CONSTRAINT rock_fk FOREIGN KEY (id)
    REFERENCES tr001_tag (id) MATCH SIMPLE
    ON UPDATE NO ACTION ON DELETE RESTRICT
);



INSERT INTO tr001_s3 (id, name, size, type, url) VALUES (1, 'The Rock.png', 123, 'image/png', 'http://rock.io/@S3/TheRock.png');
INSERT INTO tr001_user_group (user_group_id, user_group_name, user_group_status, user_group_has_permission_create_rock, user_group_has_permission_read_rock, user_group_has_permission_update_rock, user_group_has_permission_delete_rock, user_group_has_permission_create_tag, user_group_has_permission_read_tag, user_group_has_permission_update_tag, user_group_has_permission_delete_tag, user_group_has_permission_create_s3, user_group_has_permission_read_s3, user_group_has_permission_update_s3, user_group_has_permission_delete_s3, user_group_has_permission_create_user, user_group_has_permission_read_user, user_group_has_permission_update_user, user_group_has_permission_delete_user, user_group_has_permission_create_user_group, user_group_has_permission_read_user_group, user_group_has_permission_update_user_group, user_group_has_permission_delete_user_group) VALUES (1, 'ADMIN', true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true, true);
INSERT INTO tr001_user (user_id, user_full_name, user_username, user_status, user_password, user_group) VALUES (183, 'Moe Szyslak', 'moe', true, '8982d9a8880388e7e0ad9f90a18beed200ecefb77c9d9a649c1324ec42bd7bf804bc854819b085e0c1b8a9af87882bb7bff231ca2891c7c9af51518da12cbe36', 1);
