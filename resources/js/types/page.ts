export type AuthUser = {
  id?: number;
  name?: string;
  email?: string | null;
};

export type PageProps = {
  auth?: {
    user?: AuthUser | null;
    abilities?: string[];
  };
};

export type Ability = string;
